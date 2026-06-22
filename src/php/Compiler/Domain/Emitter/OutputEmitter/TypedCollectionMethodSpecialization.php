<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\SeqInterface;

use function count;

/**
 * Call-site eligibility for `phel.core` accessors on a `LocalVarNode`
 * whose inferred tag is a persistent collection type, which
 * {@see NodeEmitter\CallEmitter}
 * lowers to a direct method call instead of the runtime `cond`-chain body.
 */
final readonly class TypedCollectionMethodSpecialization
{
    /** @var array<string, string> Phel core seq accessor → PHP method */
    private const array SEQ_METHODS = [
        'first' => 'first',
        'rest' => 'rest',
    ];

    /** @var array<string, true> Tags whose runtime types implement SeqInterface */
    private const array SEQ_TAGS = [
        SeqInterface::class => true,
        PersistentVectorInterface::class => true,
        PersistentListInterface::class => true,
    ];

    private function __construct() {}

    /**
     * `(nth v i)` / `(count v)` where the analyser has tagged the
     * target as `PersistentVectorInterface`. The runtime `nth` body
     * walks a `cond` over set / seq / vector / map / php-array; for a
     * typed vector every branch collapses to a single method call.
     *
     * @return array{method: string, args: list<int>}|null list of arg
     *                                                     indices to
     *                                                     pass as
     *                                                     method args
     */
    public static function typedVectorMethodCall(CallNode $node): ?array
    {
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        $args = $node->getArguments();
        if (TagNormalizer::ofLocalVar($args[0] ?? null) !== PersistentVectorInterface::class) {
            return null;
        }

        if ($name === 'count' && count($args) === 1) {
            return ['method' => 'count', 'args' => []];
        }

        if ($name === 'nth' && count($args) === 2) {
            return ['method' => 'get', 'args' => [1]];
        }

        return null;
    }

    public static function isTypedVectorAccessor(CallNode $node): bool
    {
        if (self::typedVectorMethodCall($node) !== null) {
            return true;
        }

        if (self::isTypedVectorSecond($node)) {
            return true;
        }

        return self::isTypedVectorLast($node);
    }

    /**
     * `(second v)` where the analyser has tagged the target as
     * `PersistentVectorInterface`. The runtime `phel.core/second` is
     * `(first (next v))`, which returns nil when the vector has fewer
     * than two elements — it never throws. A bare `$v->get(1)` would
     * throw out of range, so this is only safe behind a length guard;
     * {@see NodeEmitter\Specialized\TypedCollectionCallEmitter} emits
     * `($v->count() > 1 ? $v->get(1) : null)`, preserving the nil
     * contract while collapsing the runtime `first`/`next` cond chains.
     */
    public static function isTypedVectorSecond(CallNode $node): bool
    {
        if (!PhelCoreCall::is($node, 'second')) {
            return false;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return false;
        }

        return TagNormalizer::ofLocalVar($args[0]) === PersistentVectorInterface::class;
    }

    /**
     * `(last v)` where the analyser has tagged the target as
     * `PersistentVectorInterface`. A vector is never `seq?`
     * (`seq?` covers only `LazySeqInterface` / `Cons` /
     * `PersistentListInterface`), so the runtime `phel.core/last` always
     * falls to `(peek v)`, whose vector branch is
     * `(let [n (count v)] (if (php/=== 0 n) nil (php/aget v (php/- n 1))))`
     * — an O(1) tail access, never the O(n) seq loop.
     * {@see NodeEmitter\Specialized\TypedCollectionCallEmitter} emits
     * `($v->count() === 0 ? null : $v->get($v->count() - 1))`, preserving
     * the empty → nil contract while skipping the `last` (and nested
     * `peek`) registry dispatch.
     */
    public static function isTypedVectorLast(CallNode $node): bool
    {
        if (!PhelCoreCall::is($node, 'last')) {
            return false;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return false;
        }

        return TagNormalizer::ofLocalVar($args[0]) === PersistentVectorInterface::class;
    }

    /**
     * `(first s)` / `(rest s)` where the analyser has tagged the
     * target as a `SeqInterface` / `PersistentVectorInterface` /
     * `PersistentListInterface`. The runtime body of each fn walks a
     * cond chain that handles nil, strings, php-arrays, sets, etc.;
     * for a tagged seq every branch collapses to `$s->first()` /
     * `$s->rest()`.
     */
    public static function typedSeqMethodName(CallNode $node): ?string
    {
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        if (!isset(self::SEQ_METHODS[$name])) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return null;
        }

        $tag = TagNormalizer::ofLocalVar($args[0]);
        if ($tag === null || !isset(self::SEQ_TAGS[$tag])) {
            return null;
        }

        return self::SEQ_METHODS[$name];
    }

    public static function isTypedSeqAccessor(CallNode $node): bool
    {
        return self::typedSeqMethodName($node) !== null;
    }
}
