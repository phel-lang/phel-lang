<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function count;

/**
 * Call-site eligibility for `(reduce f init v)` where the analyser has
 * tagged `v` as a `PersistentVectorInterface`, which
 * {@see NodeEmitter\Specialized\ReduceCallEmitter} lowers to a native
 * `foreach` with the step fn hoisted out of the loop.
 */
final readonly class ReduceSpecialization
{
    private function __construct() {}

    /**
     * `(reduce f init v)` on a tagged `PersistentVectorInterface`.
     *
     * Only the explicit-init arity is eligible. The runtime `phel.core/reduce`
     * body for it boxes the accumulator and an early-exit flag in two
     * `Volatile` objects, then walks the collection with a generic `dofor`,
     * paying a `deref`/`reset` pair plus a `reduced?` dispatch per element.
     * A `PersistentVectorInterface` is an `IteratorAggregate`, so all of that
     * collapses to a native `foreach` over a plain PHP accumulator.
     *
     * The 2-arity `(reduce f coll)` is deliberately excluded: it seeds the
     * accumulator from the collection and calls `(f)` with no arguments on
     * empty input, which the foreach lowering does not model.
     */
    public static function isTypedVectorReduce(CallNode $node): bool
    {
        return self::typedVectorReduce($node) !== null;
    }

    /**
     * The three operand nodes of an eligible `(reduce f init v)`, or null
     * when the call is not a typed-vector reduce.
     *
     * @return array{fn: AbstractNode, init: AbstractNode, coll: AbstractNode}|null
     */
    public static function typedVectorReduce(CallNode $node): ?array
    {
        if (!PhelCoreCall::is($node, 'reduce')) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 3) {
            return null;
        }

        if (TagNormalizer::ofLocalVar($args[2]) !== PersistentVectorInterface::class) {
            return null;
        }

        return ['fn' => $args[0], 'init' => $args[1], 'coll' => $args[2]];
    }
}
