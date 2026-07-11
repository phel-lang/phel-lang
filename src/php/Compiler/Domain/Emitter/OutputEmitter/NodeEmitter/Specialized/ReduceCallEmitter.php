<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ReduceSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Reduced;
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
        $emitter = $this->outputEmitter;

        $fnSym = Symbol::gen('reduce_fn_');
        $accSym = Symbol::gen('reduce_acc_');
        $itemSym = Symbol::gen('reduce_item_');
        $stepSym = Symbol::gen('reduce_step_');

        // `CallEmitter` has already emitted the context prefix, so the
        // lowering has to produce an expression: the loop lives in an IIFE.
        // That is one closure per `reduce` call, against a per-element saving.
        $emitter->emitFnWrapPrefix($node->getEnv(), $loc);

        // Hoist the step fn and the seed. Both are evaluated exactly once,
        // in argument order, before the collection is walked.
        $emitter->emitPhpVariable($fnSym, $loc);
        $emitter->emitStr(' = ', $loc);
        $emitter->emitNode($operands['fn']);
        $emitter->emitLine(';', $loc);

        $emitter->emitPhpVariable($accSym, $loc);
        $emitter->emitStr(' = ', $loc);
        $emitter->emitNode($operands['init']);
        $emitter->emitLine(';', $loc);

        // The tag asserts an IteratorAggregate, so the `Seq::toIterable`
        // adapter the generic foreach path uses is not needed here.
        $emitter->emitStr('foreach (', $loc);
        $emitter->emitNode($operands['coll']);
        $emitter->emitStr(' as ', $loc);
        $emitter->emitPhpVariable($itemSym, $loc);
        $emitter->emitLine(') {', $loc);
        $emitter->increaseIndentLevel();

        $emitter->emitPhpVariable($stepSym, $loc);
        $emitter->emitStr(' = (', $loc);
        $emitter->emitPhpVariable($fnSym, $loc);
        $emitter->emitStr(')(', $loc);
        $emitter->emitPhpVariable($accSym, $loc);
        $emitter->emitStr(', ', $loc);
        $emitter->emitPhpVariable($itemSym, $loc);
        $emitter->emitLine(');', $loc);

        $emitter->emitStr('if (', $loc);
        $emitter->emitPhpVariable($stepSym, $loc);
        $emitter->emitLine(' instanceof \\' . Reduced::class . ') {', $loc);
        $emitter->increaseIndentLevel();
        $emitter->emitPhpVariable($accSym, $loc);
        $emitter->emitStr(' = ', $loc);
        $emitter->emitPhpVariable($stepSym, $loc);
        $emitter->emitLine('->deref();', $loc);
        $emitter->emitLine('break;', $loc);
        $emitter->decreaseIndentLevel();
        $emitter->emitLine('}', $loc);

        $emitter->emitPhpVariable($accSym, $loc);
        $emitter->emitStr(' = ', $loc);
        $emitter->emitPhpVariable($stepSym, $loc);
        $emitter->emitLine(';', $loc);

        $emitter->decreaseIndentLevel();
        $emitter->emitLine('}', $loc);

        $emitter->emitStr('return ', $loc);
        $emitter->emitPhpVariable($accSym, $loc);
        $emitter->emitStr(';', $loc);

        $emitter->emitFnWrapSuffix($loc);

        return true;
    }
}
