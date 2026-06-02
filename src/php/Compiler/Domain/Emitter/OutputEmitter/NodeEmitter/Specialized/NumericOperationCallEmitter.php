<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NumericOperationSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function count;

/**
 * Specialisations gated by {@see NumericOperationSpecialization}: the
 * `(not (= a b))` peephole, variadic numeric / ordering chains over tagged
 * numeric locals, and the two-arg arithmetic / comparison binary ops. Each
 * collapses the `NumericOperations` polymorphic dispatch to native PHP
 * operators when the analyser has proven the operands primitive.
 */
final readonly class NumericOperationCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        if ($this->tryEmitNotEqPeephole($node)) {
            return true;
        }

        if ($this->tryEmitTypedVariadicChain($node)) {
            return true;
        }

        return $this->tryEmitTypedBinaryOp($node);
    }

    /**
     * `(not (= a b))` peephole over the typed-`=` specialiser:
     * emits `($a !== $b)` directly, skipping both `phel.core/not`
     * and the explicit `!(($a === $b))` wrapper.
     *
     * Eligibility lives on {@see NumericOperationSpecialization::notEqPeepholeInner()}.
     */
    private function tryEmitNotEqPeephole(CallNode $node): bool
    {
        $inner = NumericOperationSpecialization::notEqPeepholeInner($node);
        if (!$inner instanceof CallNode) {
            return false;
        }

        $args = $inner->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr(' !== ', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Variadic (N>=3) numeric / ordering ops over tagged numeric locals.
     * `arith` ops chain as `($a + $b + $c)` (PHP is left-associative just
     * like Phel's variadic `+`/`*`); `compare` ops expand to a pairwise
     * `&&` chain `(($a < $b) && ($b < $c))` because PHP `<` does not
     * thread its result through the next comparison the way Phel does.
     *
     * Eligibility lives on {@see NumericOperationSpecialization::typedVariadicChain()}.
     */
    private function tryEmitTypedVariadicChain(CallNode $node): bool
    {
        $spec = NumericOperationSpecialization::typedVariadicChain($node);
        if ($spec === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $op = $spec['op'];
        $this->outputEmitter->emitStr('(', $loc);

        if ($spec['kind'] === 'arith') {
            $this->outputEmitter->emitNode($args[0]);
            for ($i = 1, $n = count($args); $i < $n; ++$i) {
                $this->outputEmitter->emitStr(' ' . $op . ' ', $loc);
                $this->outputEmitter->emitNode($args[$i]);
            }
        } else {
            for ($i = 0, $n = count($args) - 1; $i < $n; ++$i) {
                if ($i > 0) {
                    $this->outputEmitter->emitStr(' && ', $loc);
                }

                $this->outputEmitter->emitStr('(', $loc);
                $this->outputEmitter->emitNode($args[$i]);
                $this->outputEmitter->emitStr(' ' . $op . ' ', $loc);
                $this->outputEmitter->emitNode($args[$i + 1]);
                $this->outputEmitter->emitStr(')', $loc);
            }
        }

        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Specialise two-arg `phel.core` arithmetic / comparison wrappers
     * to the native PHP binary op when both args are statically proven
     * primitive. The runtime defns route through `NumericOperations`
     * to handle `BigInt` / `Ratio` polymorphism; for primitive-typed
     * call sites that dispatch is wasted work and collapses to a
     * single PHP operator.
     *
     * Eligibility lives on {@see NumericOperationSpecialization::typedBinaryOpName()}.
     */
    private function tryEmitTypedBinaryOp(CallNode $node): bool
    {
        $op = NumericOperationSpecialization::typedBinaryOpName($node);
        if ($op === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr(' ' . $op . ' ', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }
}
