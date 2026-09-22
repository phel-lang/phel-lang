<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function count;

/**
 * Call-site eligibility for `(get-in coll [k1 k2 …])` where the path is a
 * syntactic literal `VectorNode`. When the target carries an inferred
 * persistent-collection tag, {@see NodeEmitter\CallEmitter} lowers it to an
 * unrolled null-coalescing subscript chain; any other target goes through
 * {@see \Phel\Lang\GetIn::path()} with the keys as a PHP array (#3320).
 *
 * The runtime `phel.core/get-in` walks a `loop`/`recur` over the path,
 * re-checking traversability and dispatching through `phel.core/get` at each
 * level. For a tagged target with a literal path every level collapses to
 * the same `($coll[($k)] ?? null)` form `php/aget` already emits — and PHP's
 * `??` consults `offsetExists` first, so the chain returns `nil` on an
 * intermediate miss (or an explicit-nil value) exactly like the runtime,
 * without ever throwing on a missing key.
 *
 * @internal
 */
final readonly class GetInSpecialization
{
    private function __construct() {}

    public static function isLiteralPathGetIn(CallNode $node): bool
    {
        return self::literalPathLookupKeys($node) !== null;
    }

    /**
     * Returns the literal path key nodes for any `(get-in coll [k1 k2 …])` or
     * `(get-in coll [k1 k2 …] not-found)` call, tagged target or not, or
     * `null` when the path is not a literal vector.
     *
     * An empty path qualifies: `GetIn::path` answers the target for it, as
     * the runtime does. A path carrying metadata does not, since its meta
     * form is evaluated with the vector and the lowering never builds one.
     *
     * @return list<AbstractNode>|null
     */
    public static function literalPathLookupKeys(CallNode $node): ?array
    {
        if (!PhelCoreCall::is($node, 'get-in')) {
            return null;
        }

        $args = $node->getArguments();
        $argc = count($args);
        if ($argc !== 2 && $argc !== 3) {
            return null;
        }

        $path = $args[1];
        if (!$path instanceof VectorNode || $path->getMeta() instanceof MapNode) {
            return null;
        }

        return $path->getArgs();
    }

    /**
     * Returns the literal path key nodes for a `(get-in coll [k1 k2 …])`
     * call the emitter can unroll into a subscript chain, or `null` when the
     * call takes {@see \Phel\Lang\GetIn::path()} or the runtime call.
     *
     * Eligibility, on top of {@see self::literalPathLookupKeys()}:
     *  - two args only (`nil` is the only default the null-coalescing chain
     *    can express);
     *  - the target is a `LocalVarNode` tagged `PersistentMapInterface` or
     *    `PersistentVectorInterface`;
     *  - the path is not empty (`GetIn::path` answers the target for it).
     *
     * @return list<AbstractNode>|null
     */
    public static function literalPathKeys(CallNode $node): ?array
    {
        $keys = self::literalPathLookupKeys($node);
        if ($keys === null || $keys === [] || count($node->getArguments()) !== 2) {
            return null;
        }

        // `ofNode`: `GetInCallEmitter::tryEmit` emits the target once.
        $tag = TagNormalizer::ofNode($node->getArguments()[0]);
        if ($tag !== PersistentMapInterface::class && $tag !== PersistentVectorInterface::class) {
            return null;
        }

        return $keys;
    }
}
