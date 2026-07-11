<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ReduceSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Reduced;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

/**
 * Specialisation gated by {@see ReduceSpecialization}: `(reduce f init v)`
 * on a tagged `PersistentVectorInterface`, lowered to a native `foreach`.
 *
 * The runtime `phel.core/reduce` pays, per element, a `Volatile` deref plus
 * reset for the accumulator, a second `Volatile` deref for the early-exit
 * flag, and a `reduced?` dispatch — all on top of a generic `dofor` walk.
 * The lowering hoists the step fn into a local (resolved once, not per
 * element), keeps the accumulator in a plain PHP variable, and iterates the
 * vector directly, since `PersistentVectorInterface` is an `IteratorAggregate`.
 *
 * The `reduced` early-termination contract is preserved: a `Reduced` result
 * unwraps into the accumulator and breaks the loop.
 */
final readonly class ReduceCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        $operands = ReduceSpecialization::typedVectorReduce($node);
        if ($operands === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();

        $fnSym = Symbol::gen('reduce_fn_');
        $accSym = Symbol::gen('reduce_acc_');

        // `CallEmitter` has already emitted the context prefix, so the lowering
        // has to produce an expression: the loop lives in an IIFE. That is one
        // closure per `reduce` call, against a per-element saving. Locals reached
        // by `(php/ref x)` in an operand must be captured by reference, or the
        // by-ref write lands in the closure's copy and is lost.
        $this->outputEmitter->emitFnWrapPrefix(
            $node->getEnv(),
            $loc,
            new ByRefLocalCollector()->collect($node),
        );

        // Hoist the step fn and the seed out of the loop. Both are evaluated
        // exactly once, in argument order, before the collection is walked.
        $this->emitAssign($fnSym, $operands['fn'], $loc);
        $this->emitAssign($accSym, $operands['init'], $loc);

        $this->emitLoop($operands['coll'], $fnSym, $accSym, $loc);

        $this->outputEmitter->emitStr('return ', $loc);
        $this->outputEmitter->emitPhpVariable($accSym, $loc);
        $this->outputEmitter->emitStr(';', $loc);

        $this->outputEmitter->emitFnWrapSuffix($loc);

        return true;
    }

    /**
     * `foreach ($coll as $item) { … }`, folding each element into `$accSym`
     * through `$fnSym` and honouring a `Reduced` result as an early exit.
     *
     * The collection tag asserts an `IteratorAggregate`, so the
     * `Seq::toIterable` adapter the generic foreach path uses is not needed.
     */
    private function emitLoop(AbstractNode $coll, Symbol $fnSym, Symbol $accSym, ?SourceLocation $loc): void
    {
        $itemSym = Symbol::gen('reduce_item_');
        $stepSym = Symbol::gen('reduce_step_');

        $this->outputEmitter->emitStr('foreach (', $loc);
        $this->outputEmitter->emitNode($coll);
        $this->outputEmitter->emitStr(' as ', $loc);
        $this->outputEmitter->emitPhpVariable($itemSym, $loc);
        $this->outputEmitter->emitLine(') {', $loc);
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitPhpVariable($stepSym, $loc);
        $this->outputEmitter->emitStr(' = (', $loc);
        $this->outputEmitter->emitPhpVariable($fnSym, $loc);
        $this->outputEmitter->emitStr(')(', $loc);
        $this->outputEmitter->emitPhpVariable($accSym, $loc);
        $this->outputEmitter->emitStr(', ', $loc);
        $this->outputEmitter->emitPhpVariable($itemSym, $loc);
        $this->outputEmitter->emitLine(');', $loc);

        $this->emitReducedGuard($accSym, $stepSym, $loc);

        $this->emitAssignVariable($accSym, $stepSym, $loc);

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $loc);
    }

    /**
     * `if ($step instanceof Reduced) { $acc = $step->deref(); break; }` — the
     * `(reduced x)` early-termination contract of the runtime `reduce`.
     */
    private function emitReducedGuard(Symbol $accSym, Symbol $stepSym, ?SourceLocation $loc): void
    {
        $this->outputEmitter->emitStr('if (', $loc);
        $this->outputEmitter->emitPhpVariable($stepSym, $loc);
        $this->outputEmitter->emitLine(' instanceof \\' . Reduced::class . ') {', $loc);
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitPhpVariable($accSym, $loc);
        $this->outputEmitter->emitStr(' = ', $loc);
        $this->outputEmitter->emitPhpVariable($stepSym, $loc);
        $this->outputEmitter->emitLine('->deref();', $loc);
        $this->outputEmitter->emitLine('break;', $loc);

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $loc);
    }

    private function emitAssign(Symbol $target, AbstractNode $value, ?SourceLocation $loc): void
    {
        $this->outputEmitter->emitPhpVariable($target, $loc);
        $this->outputEmitter->emitStr(' = ', $loc);
        $this->outputEmitter->emitNode($value);
        $this->outputEmitter->emitLine(';', $loc);
    }

    private function emitAssignVariable(Symbol $target, Symbol $source, ?SourceLocation $loc): void
    {
        $this->outputEmitter->emitPhpVariable($target, $loc);
        $this->outputEmitter->emitStr(' = ', $loc);
        $this->outputEmitter->emitPhpVariable($source, $loc);
        $this->outputEmitter->emitLine(';', $loc);
    }
}
