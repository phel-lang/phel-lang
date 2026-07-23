<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function array_slice;
use function array_unshift;
use function count;

/**
 * Call-site eligibility for `assoc` / `conj` / `dissoc` on a
 * `LocalVarNode` tagged with a persistent-collection type, which
 * {@see NodeEmitter\CallEmitter}
 * lowers to a direct method call (single call) or a batched transient
 * chain (`(-> coll (assoc …) (assoc …) …)`).
 */
final readonly class AssocConjSpecialization
{
    private function __construct() {}

    /**
     * `(assoc coll k v)` 3-arg / `(conj coll x)` 2-arg / `(push coll x)`
     * 2-arg / `(dissoc coll k)` 2-arg specialise to a direct method call
     * when the target carries an inferred persistent-collection tag:
     *
     *  - `PersistentMapInterface`  → `put` / `remove`
     *  - `PersistentVectorInterface` → `update` / `append`
     *
     * `push` is the deprecated alias for the 2-arg `conj` (its body is
     * `(conj coll x)`), so on a vector it appends exactly like `conj`.
     *
     * Variadic `dissoc` is handled by {@see self::typedDissocKeys()};
     * variadic `assoc` / `conj` need a runtime loop and are not specialised
     * here.
     */
    public static function typedAssocConjDissocMethod(CallNode $node): ?string
    {
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        $args = $node->getArguments();
        $tag = TagNormalizer::ofLocalVar($args[0] ?? null);
        if ($tag === null) {
            return null;
        }

        $argCount = count($args);

        if ($name === 'assoc' && $argCount === 3) {
            return match ($tag) {
                PersistentMapInterface::class => 'put',
                PersistentVectorInterface::class => 'update',
                default => null,
            };
        }

        if ($name === 'conj' && $argCount === 2) {
            return match ($tag) {
                PersistentVectorInterface::class => 'append',
                default => null,
            };
        }

        if ($name === 'dissoc' && $argCount === 2 && $tag === PersistentMapInterface::class) {
            return 'remove';
        }

        return null;
    }

    public static function isTypedAssocConjDissoc(CallNode $node): bool
    {
        return self::typedAssocConjDissocMethod($node) !== null;
    }

    /**
     * `(dissoc m k1 k2 …)` on a `PersistentMapInterface`-tagged target with
     * one or more keys specialises to a chain of `->remove($k)` calls. The
     * runtime `dissoc` loops `dissoc-one` over the keys left-to-right, each
     * step calling `remove` on the map, so the emitted chain preserves key
     * order.
     *
     * The single-key arity overlaps {@see self::typedAssocConjDissocMethod()}
     * — both lower to the same `->remove($k)` — but the emitter consults
     * this predicate first, so the variadic emitter owns every typed
     * `dissoc`.
     *
     * @return list<AbstractNode>|null the key argument nodes, or null when
     *                                 the call is not a typed `dissoc`
     */
    public static function typedDissocKeys(CallNode $node): ?array
    {
        if (!PhelCoreCall::is($node, 'dissoc')) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) < 2) {
            return null;
        }

        if (TagNormalizer::ofLocalVar($args[0]) !== PersistentMapInterface::class) {
            return null;
        }

        return array_slice($args, 1);
    }

    public static function isTypedDissocKeys(CallNode $node): bool
    {
        return self::typedDissocKeys($node) !== null;
    }

    /**
     * Detects an `(-> m (assoc k v) (assoc k v) ...)` or
     * `(-> v (conj x) (conj y) ...)` chain — after thread-macro
     * expansion these are nested `CallNode`s of the same op terminating
     * at a `LocalVarNode` whose tag matches `PersistentMapInterface` or
     * `PersistentVectorInterface`. A chain of length 1 is **not**
     * batched: the existing single-call specialiser already lowers it
     * to a direct method call, and a transient round-trip would just
     * add work.
     *
     * Returns the leaf target, the transient method to spam, and the
     * argument groups (`[k, v]` pairs for `assoc`, single-element
     * `[x]` lists for `conj`) — `null` when the call is not a chain.
     *
     * @return array{
     *     target: LocalVarNode,
     *     method: 'append'|'put',
     *     groups: list<list<AbstractNode>>
     * }|null
     */
    public static function assocConjChain(CallNode $node): ?array
    {
        $shape = self::classifyChainHead($node);
        if ($shape === null) {
            return null;
        }

        [$opName, $expectedArgCount, $method, $expectedTag] = $shape;

        $groups = [];
        $current = $node;
        while (true) {
            if (!PhelCoreCall::is($current, $opName)) {
                return null;
            }

            $cArgs = $current->getArguments();
            if (count($cArgs) !== $expectedArgCount) {
                return null;
            }

            array_unshift($groups, array_slice($cArgs, 1));

            $target = $cArgs[0];
            if ($target instanceof CallNode) {
                $current = $target;
                continue;
            }

            if (!$target instanceof LocalVarNode) {
                return null;
            }

            if (TagNormalizer::normalise($target->getInferredType()) !== $expectedTag) {
                return null;
            }

            if (count($groups) < 2) {
                return null;
            }

            return [
                'target' => $target,
                'method' => $method,
                'groups' => $groups,
            ];
        }
    }

    public static function isAssocConjChain(CallNode $node): bool
    {
        return self::assocConjChain($node) !== null;
    }

    /**
     * @return array{0: string, 1: int, 2: 'append'|'put', 3: class-string}|null
     */
    private static function classifyChainHead(CallNode $node): ?array
    {
        return match (PhelCoreCall::nameOf($node)) {
            'assoc' => ['assoc', 3, 'put', PersistentMapInterface::class],
            'conj' => ['conj', 2, 'append', PersistentVectorInterface::class],
            default => null,
        };
    }
}
