<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Shared\CompilerConstants;

use function count;

/**
 * Call-site eligibility for `(get-in coll [k1 k2 …])` where the path is a
 * syntactic literal `VectorNode` and the target carries an inferred
 * persistent-collection tag, which {@see NodeEmitter\CallEmitter} lowers to
 * an unrolled null-coalescing subscript chain.
 *
 * The runtime `phel.core/get-in` walks a `loop`/`recur` over the path,
 * re-checking traversability and dispatching through `phel.core/get` at each
 * level. For a tagged target with a literal path every level collapses to
 * the same `($coll[($k)] ?? null)` form `php/aget` already emits — and PHP's
 * `??` consults `offsetExists` first, so the chain returns `nil` on an
 * intermediate miss (or an explicit-nil value) exactly like the runtime,
 * without ever throwing on a missing key.
 */
final readonly class GetInSpecialization
{
    private function __construct() {}

    public static function isLiteralPathGetIn(CallNode $node): bool
    {
        return self::literalPathKeys($node) !== null;
    }

    /**
     * Returns the literal path key nodes for a `(get-in coll [k1 k2 …])`
     * call the emitter can specialise, or `null` when the call is not an
     * eligible literal-path get-in.
     *
     * Eligibility:
     *  - fn is `phel.core/get-in`, two args only (the 3-arg `opt` default
     *    form keeps the runtime path — `nil` is the only default the
     *    null-coalescing chain can express);
     *  - the target is a `LocalVarNode` tagged `PersistentMapInterface` or
     *    `PersistentVectorInterface`;
     *  - the path is a non-empty literal `VectorNode` (an empty path returns
     *    the target as-is, which the runtime handles).
     *
     * @return list<AbstractNode>|null
     */
    public static function literalPathKeys(CallNode $node): ?array
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'get-in'
        ) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 2) {
            return null;
        }

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return null;
        }

        $tag = TagNormalizer::normalise($target->getInferredType());
        if ($tag !== PersistentMapInterface::class && $tag !== PersistentVectorInterface::class) {
            return null;
        }

        $path = $args[1];
        if (!$path instanceof VectorNode) {
            return null;
        }

        $keys = $path->getArgs();
        if ($keys === []) {
            return null;
        }

        return $keys;
    }
}
