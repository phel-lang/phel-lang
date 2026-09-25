<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Closure;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\GuardedCoreCallSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Symbol;

use function array_any;

/**
 * Emits the guarded fast path of {@see GuardedCoreCallSpecialization}: the
 * native PHP expression behind an `is_*` guard, with the
 * runtime call as the fallback (#3351).
 *
 * Each operand is read more than once (by the guard, the native operator
 * and the fallback). A local or a literal is read in place. Anything else
 * is assigned to a fresh temporary on its first read, and every first read
 * sits where PHP always evaluates it, left to right, before the guard can
 * short-circuit: the operands of `===` / `!==`, or a non-short-circuiting
 * `&` between the guards. So each operand still runs exactly once, in
 * source order, whichever branch is taken.
 *
 * Not one of the {@see SpecializedCallEmitterInterface} families: the
 * fallback calls the runtime fn, so the call keeps its `$__phel_call_N`
 * slot and the callee is written by the generic path's own emitter.
 *
 * @internal
 */
final readonly class GuardedCoreCallEmitter
{
    /**
     * @param Closure(CallNode):void $emitCallee writes the callee the way the
     *                                           generic call path does
     */
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private Closure $emitCallee,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        $name = GuardedCoreCallSpecialization::nameOf($node);
        if ($name === null) {
            return false;
        }

        $operands = $this->operandsOf($node);

        $orderingOp = GuardedCoreCallSpecialization::orderingOperator($name);
        if ($orderingOp !== null) {
            $this->emitOrdering($node, $operands, $orderingOp);
            return true;
        }

        if ($name === '=' || $name === 'not=') {
            $this->emitEquality($node, $operands, $name === 'not=');
            return true;
        }

        $this->emitUnary($node, $operands[0], $name);
        return true;
    }

    /**
     * `(\is_scalar($a) && \is_scalar($b) ? ($a < $b) : <runtime call>)`
     *
     * @param list<array{AbstractNode, ?Symbol}> $operands
     */
    private function emitOrdering(CallNode $node, array $operands, string $op): void
    {
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->emitGuards($operands, GuardedCoreCallSpecialization::guardFor($op), true);
        $this->outputEmitter->emitStr(' ? (', $loc);
        $this->emitRead($operands[0]);
        $this->outputEmitter->emitStr(' ' . $op . ' ', $loc);
        $this->emitRead($operands[1]);
        $this->outputEmitter->emitStr(') : ', $loc);
        $this->emitRuntimeCall($node, $operands);
        $this->outputEmitter->emitStr(')', $loc);
    }

    /**
     * `(= a b)` answers `true` on identical values without asking the
     * runtime: `=` is reflexive for everything `===` accepts, and `NaN`, the
     * one value that is not, fails `===` too. It answers `false` when the
     * guard pins both sides to one native type (two ints, an int against an
     * int literal, a string against a string literal), where `===` is the
     * whole of Phel equality. The rest, `(= 1 (bigint 1))` among them, asks
     * the runtime fn. `not=` is the mirror image, over the runtime `not=`:
     *
     *     ($a === 1 || (!(\is_int($a)) && <runtime =>))
     *     ($a !== 1 && ((\is_int($a)) || <runtime not=>))
     *
     * @param list<array{AbstractNode, ?Symbol}> $operands
     */
    private function emitEquality(CallNode $node, array $operands, bool $negate): void
    {
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->emitFirstRead($operands[0]);
        $this->outputEmitter->emitStr($negate ? ' !== ' : ' === ', $loc);
        $this->emitFirstRead($operands[1]);
        $this->outputEmitter->emitStr($negate ? ' && ((' : ' || (!(', $loc);
        $this->emitEqualityGuards($operands);
        $this->outputEmitter->emitStr($negate ? ') || ' : ') && ', $loc);
        $this->emitRuntimeCall($node, $operands);
        $this->outputEmitter->emitStr('))', $loc);
    }

    /**
     * `zero?` / `pos?` / `neg?`, under the guard each one needs:
     *
     *     (\is_int($x) ? ($x === 0) : <runtime call>)
     *
     * `inc` / `dec`, where the bound is left to the runtime fn, which
     * promotes to `BigInt` there:
     *
     *     (\is_int($x) && $x !== \PHP_INT_MAX ? ($x + 1) : <runtime call>)
     *
     * @param array{AbstractNode, ?Symbol} $operand
     */
    private function emitUnary(CallNode $node, array $operand, string $name): void
    {
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(' . GuardedCoreCallSpecialization::guardFor($name) . '(', $loc);
        $this->emitFirstRead($operand);

        $predicate = GuardedCoreCallSpecialization::numericPredicateSuffix($name);
        if ($predicate !== null) {
            $this->outputEmitter->emitStr(') ? (', $loc);
            $this->emitRead($operand);
            $this->outputEmitter->emitStr($predicate . ') : ', $loc);
        } else {
            $isInc = $name === 'inc';
            $this->outputEmitter->emitStr(') && ', $loc);
            $this->emitRead($operand);
            $this->outputEmitter->emitStr($isInc ? ' !== \\PHP_INT_MAX ? (' : ' !== \\PHP_INT_MIN ? (', $loc);
            $this->emitRead($operand);
            $this->outputEmitter->emitStr($isInc ? ' + 1) : ' : ' - 1) : ', $loc);
        }

        $this->emitRuntimeCall($node, [$operand]);
        $this->outputEmitter->emitStr(')', $loc);
    }

    /**
     * `\is_int(<a>) && \is_int(<b>)` (or another `is_*`) over the
     * non-literal operands; a literal needs no guard, its type is known.
     * When an operand is a temporary and this is where it is first read, the
     * guards are joined with `&` so every assignment runs even after a guard
     * has failed.
     *
     * @param list<array{AbstractNode, ?Symbol}> $operands
     */
    private function emitGuards(array $operands, string $guard, bool $isFirstRead): void
    {
        $joiner = $isFirstRead && $this->hasTemporary($operands) ? ' & ' : ' && ';
        $first = true;
        foreach ($operands as $operand) {
            if ($operand[0] instanceof LiteralNode) {
                continue;
            }

            $loc = $operand[0]->getStartSourceLocation();
            $this->outputEmitter->emitStr(($first ? '' : $joiner) . $guard . '(', $loc);
            if ($isFirstRead) {
                $this->emitFirstRead($operand);
            } else {
                $this->emitRead($operand);
            }

            $this->outputEmitter->emitStr(')', $loc);
            $first = false;
        }
    }

    /**
     * The non-literal operand checked against the literal's type, or both
     * operands checked as ints.
     *
     * @param list<array{AbstractNode, ?Symbol}> $operands
     */
    private function emitEqualityGuards(array $operands): void
    {
        $guard = GuardedCoreCallSpecialization::literalGuard($operands[0][0])
            ?? GuardedCoreCallSpecialization::literalGuard($operands[1][0]);
        if ($guard === null) {
            $this->emitGuards($operands, '\\is_int', false);
            return;
        }

        $operand = $operands[0][0] instanceof LiteralNode ? $operands[1] : $operands[0];
        $loc = $operand[0]->getStartSourceLocation();
        $this->outputEmitter->emitStr($guard . '(', $loc);
        $this->emitRead($operand);
        $this->outputEmitter->emitStr(')', $loc);
    }

    /**
     * @param list<array{AbstractNode, ?Symbol}> $operands
     */
    private function emitRuntimeCall(CallNode $node, array $operands): void
    {
        ($this->emitCallee)($node);

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('->__invoke(', $loc);
        foreach ($operands as $i => $operand) {
            if ($i > 0) {
                $this->outputEmitter->emitStr(', ', $loc);
            }

            $this->emitRead($operand);
        }

        $this->outputEmitter->emitStr(')', $loc);
    }

    /**
     * @param array{AbstractNode, ?Symbol} $operand
     */
    private function emitFirstRead(array $operand): void
    {
        [$node, $temp] = $operand;
        if (!$temp instanceof Symbol) {
            $this->outputEmitter->emitNode($node);
            return;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitPhpVariable($temp, $loc);
        $this->outputEmitter->emitStr(' = ', $loc);
        $this->outputEmitter->emitNode($node);
        $this->outputEmitter->emitStr(')', $loc);
    }

    /**
     * @param array{AbstractNode, ?Symbol} $operand
     */
    private function emitRead(array $operand): void
    {
        [$node, $temp] = $operand;
        if ($temp instanceof Symbol) {
            $this->outputEmitter->emitPhpVariable($temp, $node->getStartSourceLocation());
            return;
        }

        $this->outputEmitter->emitNode($node);
    }

    /**
     * Pairs each argument with the temporary it is spilled into, or `null`
     * when it can be read in place.
     *
     * @return list<array{AbstractNode, ?Symbol}>
     */
    private function operandsOf(CallNode $node): array
    {
        $operands = [];
        foreach ($node->getArguments() as $arg) {
            $inPlace = $arg instanceof LocalVarNode || $arg instanceof LiteralNode;
            $operands[] = [$arg, $inPlace ? null : Symbol::gen('__phel_guard_')];
        }

        return $operands;
    }

    /**
     * @param list<array{AbstractNode, ?Symbol}> $operands
     */
    private function hasTemporary(array $operands): bool
    {
        return array_any($operands, static fn(array $operand): bool => $operand[1] instanceof Symbol);
    }
}
