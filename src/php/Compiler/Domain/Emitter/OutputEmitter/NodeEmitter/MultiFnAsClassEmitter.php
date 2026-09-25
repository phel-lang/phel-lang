<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\AbstractFn;
use Phel\Lang\Symbol;

use function assert;
use function count;
use function implode;
use function str_starts_with;

/**
 * A multi-arity fn compiles to an `AbstractFn` subclass with one method per
 * arity (#3355). Captured locals are constructor-promoted properties, read
 * back at the top of each method, as for a single-arity fn class.
 *
 * An arity whose PHP signature can override the fixed `invokeArityN` slot
 * (no param type, no by-ref param, see
 * {@see MethodEmitter::hasArityMethodCompatibleSignature()}) is emitted as
 * that method, so a call site that has proven the arity reaches the body in
 * one call. Any other arity, the variadic one and fixed arities above
 * `AbstractFn::MAX_ARITY_SLOT` get a method of their own, which `__invoke`
 * and, for a typed fixed arity, the `invokeArityN` slot call.
 *
 * Arity bodies used to be closures built in the constructor and stored in
 * properties, which allocated one `Closure` per arity every time the fn
 * value was created: every `(comp f g)`, `(partial f x)`, or local
 * multi-arity fn.
 *
 * @internal
 */
final readonly class MultiFnAsClassEmitter implements NodeEmitterInterface
{
    private const string VARIADIC_METHOD = 'phelArityVariadic';

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private MethodEmitter $methodEmitter,
        private ClosureEmitterHelper $closureHelper,
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof MultiFnNode);

        $fnNodes = $node->getFnNodes();
        $uses = $this->collectUses($fnNodes);

        $this->emitClassBegin($node, $uses);
        $this->emitProperties($node, $uses);
        $this->closureHelper->emitConstructor($uses, $node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitLine();
        $this->emitInvoke($node, $fnNodes);
        $this->emitArityMethods($node, $fnNodes);
        $this->emitClassEnd($node);
    }

    /**
     * @param list<FnNode> $fnNodes
     *
     * @return list<Symbol>
     */
    private function collectUses(array $fnNodes): array
    {
        $byName = [];   // name => first Use instance seen

        foreach ($fnNodes as $fnNode) {
            foreach ($fnNode->getUses() as $use) {
                $name = $use->getName();
                $byName[$name] ??= $use;
            }
        }

        return array_values($byName);
    }

    /**
     * @param list<Symbol> $uses
     */
    private function emitClassBegin(MultiFnNode $node, array $uses): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('new class(', $node->getStartSourceLocation());

        $this->closureHelper->emitConstructorArguments($uses, $node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitLine(') extends \\Phel\\Lang\\AbstractFn {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->enterClassScope();
    }

    /**
     * @param list<Symbol> $uses
     */
    private function emitProperties(MultiFnNode $node, array $uses): void
    {
        $ns = addslashes($this->outputEmitter->mungeEncodePhpNs($node->getEnv()->getBoundTo()));
        $this->outputEmitter->emitLine('public const BOUND_TO = "' . $ns . '";', $node->getStartSourceLocation());

        $this->closureHelper->emitProperties($uses, $node->getEnv(), $node->getStartSourceLocation());
    }

    /**
     * Multi-arity dispatch via a `match` jump table. PHP compiles `match` to
     * a single branchless table when every arm is a constant `int`, and the
     * variadic tail collapses into the `default` arm.
     *
     * @param list<FnNode> $fnNodes
     */
    private function emitInvoke(MultiFnNode $node, array $fnNodes): void
    {
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitLine('public function __invoke(...$args) {', $loc);
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('return match (\\count($args)) {', $loc);
        $this->outputEmitter->increaseIndentLevel();

        $variadic = null;
        foreach ($fnNodes as $fnNode) {
            if ($fnNode->isVariadic()) {
                $variadic = $fnNode;
                continue;
            }

            $arity = count($fnNode->getParams());
            $params = [];
            for ($p = 0; $p < $arity; ++$p) {
                $params[] = '$args[' . $p . ']';
            }

            $this->outputEmitter->emitLine(
                $arity . ' => $this->' . $this->bodyMethodName($fnNode) . '(' . implode(', ', $params) . '),',
                $loc,
            );
        }

        if ($variadic instanceof FnNode) {
            $this->outputEmitter->emitLine(
                'default => \\count($args) >= ' . $variadic->getMinArity()
                . ' ? $this->' . self::VARIADIC_METHOD . '(...$args)'
                . ' : throw new \\InvalidArgumentException("No matching function arity"),',
                $loc,
            );
        } else {
            $this->outputEmitter->emitLine(
                'default => throw new \\InvalidArgumentException("No matching function arity"),',
                $loc,
            );
        }

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('};', $loc);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $loc);
    }

    /**
     * One method per arity holding its body, plus an `invokeArityN` slot
     * forwarding to a fixed arity whose body could not take the slot's
     * signature itself. Arities without a slot of their own (the variadic
     * one, fixed ones above `MAX_ARITY_SLOT`) keep the inherited default,
     * which routes back through `__invoke`.
     *
     * @param list<FnNode> $fnNodes
     */
    private function emitArityMethods(MultiFnNode $node, array $fnNodes): void
    {
        foreach ($fnNodes as $fnNode) {
            $methodName = $this->bodyMethodName($fnNode);

            $this->outputEmitter->emitLine();
            $this->methodEmitter->emit(
                $methodName,
                $fnNode,
                $this->isAritySlot($methodName) ? 'mixed' : null,
            );

            if (!$fnNode->isVariadic() && !$this->isAritySlot($methodName) && $this->hasAritySlot($fnNode)) {
                $this->emitAritySlotForwarder($node, $fnNode, $methodName);
            }
        }
    }

    /**
     * The name of the method holding an arity's body: the `invokeArityN`
     * slot itself when the signature allows it, otherwise `phelArityN` /
     * `phelArityVariadic`.
     */
    private function bodyMethodName(FnNode $fnNode): string
    {
        if ($fnNode->isVariadic()) {
            return self::VARIADIC_METHOD;
        }

        $arity = count($fnNode->getParams());
        if ($this->hasAritySlot($fnNode) && $this->methodEmitter->hasArityMethodCompatibleSignature($fnNode)) {
            return 'invokeArity' . $arity;
        }

        return 'phelArity' . $arity;
    }

    private function hasAritySlot(FnNode $fnNode): bool
    {
        return count($fnNode->getParams()) <= AbstractFn::MAX_ARITY_SLOT;
    }

    private function isAritySlot(string $methodName): bool
    {
        return str_starts_with($methodName, 'invokeArity');
    }

    /**
     * `public function invokeArityN(mixed $a1, ...): mixed` calling the
     * arity's own method, whose typed or by-ref params PHP then checks as it
     * did on the closure.
     */
    private function emitAritySlotForwarder(MultiFnNode $node, FnNode $fnNode, string $methodName): void
    {
        $loc = $node->getStartSourceLocation();
        $arity = count($fnNode->getParams());

        $params = [];
        $typedParams = [];
        for ($p = 1; $p <= $arity; ++$p) {
            $params[] = '$a' . $p;
            $typedParams[] = 'mixed $a' . $p;
        }

        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine(
            'public function invokeArity' . $arity . '(' . implode(', ', $typedParams) . '): mixed {',
            $loc,
        );
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('return $this->' . $methodName . '(' . implode(', ', $params) . ');', $loc);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $loc);
    }

    private function emitClassEnd(MultiFnNode $node): void
    {
        $this->outputEmitter->exitClassScope();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
