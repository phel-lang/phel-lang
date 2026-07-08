<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Lang\Keyword;
use SplObjectStorage;

use function count;
use function is_bool;
use function is_float;
use function is_int;
use function is_nan;
use function is_string;
use function strlen;
use function var_export;

/**
 * Per-fn-body scope tracking which nodes will be hoisted to a `static` PHP
 * slot. Holds two independent slot tables:
 *
 *  - constant slots: pure collection literals lifted to `$__phel_const_N`,
 *    so each fn allocation reuses a single immutable instance.
 *  - call-site slots: dynamic global-fn callee resolutions lifted to
 *    `$__phel_call_N`, so each call site reuses the resolved `AbstractFn`
 *    after the first invocation instead of paying the registry lookup.
 *
 * Counters and lookups are independent so the emitter can render distinct
 * `static $__phel_const_*` and `static $__phel_call_*` declarations.
 *
 * Constant slots dedup cacheable literals **by value**: scalars/keywords key
 * on their value, and pure collection literals key on a structural digest of
 * their contents, so every occurrence of the same literal collapses to a
 * single shared slot — one `??=` guard and one `use (&...)` capture entry.
 * Both colliding sites emit the identical literal but share the variable, so
 * only the first evaluation allocates. The per-node map still points every
 * collapsed node at the shared slot, so the emitter resolves slots by node as
 * before and never references an uncaptured slot.
 */
final class ConstantScope
{
    /** @var SplObjectStorage<AbstractNode, int> */
    private SplObjectStorage $constSlots;

    /** @var SplObjectStorage<AbstractNode, int> */
    private SplObjectStorage $callSlots;

    /**
     * Value key (for dedup-by-value literals) → shared slot id.
     *
     * @var array<string, int>
     */
    private array $valueSlots = [];

    private int $nextConstId = 0;

    private int $nextCallId = 0;

    public function __construct()
    {
        $this->constSlots = new SplObjectStorage();
        $this->callSlots = new SplObjectStorage();
    }

    public function reserve(AbstractNode $node): int
    {
        if ($this->constSlots->offsetExists($node)) {
            /** @var int $existing */
            $existing = $this->constSlots[$node];
            return $existing;
        }

        $valueKey = $this->valueKey($node);
        if ($valueKey !== null) {
            // Cacheable scalar/keyword/collection literal: dedup by value.
            // Reuse the shared slot if this value was already reserved,
            // otherwise allocate one. Either way the per-node map records the
            // shared slot so `lookup($node)` resolves every occurrence to it
            // and the emitter never references an uncaptured slot.
            $slot = $this->valueSlots[$valueKey] ??= $this->nextConstId++;
            $this->constSlots[$node] = $slot;

            return $slot;
        }

        $this->constSlots[$node] = $this->nextConstId++;
        return $this->constSlots[$node];
    }

    public function lookup(AbstractNode $node): ?int
    {
        if (!$this->constSlots->offsetExists($node)) {
            return null;
        }

        /** @var int $id */
        $id = $this->constSlots[$node];
        return $id;
    }

    public function count(): int
    {
        return $this->nextConstId;
    }

    public function reserveCallSlot(AbstractNode $node): int
    {
        if (!$this->callSlots->offsetExists($node)) {
            $this->callSlots[$node] = $this->nextCallId++;
        }

        /** @var int $id */
        $id = $this->callSlots[$node];
        return $id;
    }

    public function lookupCallSlot(AbstractNode $node): ?int
    {
        if (!$this->callSlots->offsetExists($node)) {
            return null;
        }

        /** @var int $id */
        $id = $this->callSlots[$node];
        return $id;
    }

    public function callSlotCount(): int
    {
        return $this->nextCallId;
    }

    /**
     * Stable value key for a cacheable literal, or `null` for any node that
     * must stay identity-keyed (symbols, calls, numeric value types, etc.).
     * Scalars/keywords key on their value; pure collection literals key on a
     * structural digest of their contents (each child recursed through the
     * same method). Type-tagged so `1` (int) and `"1"` (string) never collide,
     * keywords never collide with a same-named string, and the `vec:`/`set:`/
     * `map:` prefixes keep the collection shapes disjoint.
     */
    private function valueKey(AbstractNode $node): ?string
    {
        if ($node instanceof VectorNode) {
            return $this->collectionValueKey('vec', $node->getArgs());
        }

        if ($node instanceof SetNode) {
            return $this->collectionValueKey('set', $node->getValues());
        }

        if ($node instanceof MapNode) {
            return $this->mapValueKey($node->getKeyValues());
        }

        if (!$node instanceof LiteralNode) {
            return null;
        }

        $value = $node->getValue();

        if ($value instanceof Keyword) {
            $namespace = $value->getNamespace();
            return $namespace !== null
                ? 'kw:' . $namespace . '/' . $value->getName()
                : 'kw:' . $value->getName();
        }

        if (is_string($value)) {
            return 'str:' . $value;
        }

        if (is_int($value)) {
            return 'int:' . $value;
        }

        if (is_float($value)) {
            // NaN != NaN, so two NaN literals must stay distinct instances;
            // interning would alias them and flip collection equality to true.
            if (is_nan($value)) {
                return null;
            }

            // `var_export` round-trips a float deterministically (INF
            // included), so distinct floats never share a slot key.
            return 'float:' . var_export($value, true);
        }

        if (is_bool($value)) {
            return 'bool:' . ($value ? '1' : '0');
        }

        if ($value === null) {
            return 'nil';
        }

        return null;
    }

    /**
     * Structural key for a `VectorNode`/`SetNode`. Returns `null` — so the
     * whole literal stays identity-keyed — if any element is not itself
     * value-keyable (e.g. a call or local): sharing a slot only holds when
     * the literal reconstructs the same value on every evaluation.
     *
     * @param list<AbstractNode> $children
     */
    private function collectionValueKey(string $prefix, array $children): ?string
    {
        $digest = '';
        foreach ($children as $child) {
            $childKey = $this->valueKey($child);
            if ($childKey === null) {
                return null;
            }

            $digest .= $this->lengthPrefixedKey($childKey);
        }

        return $prefix . ':[' . $digest . ']';
    }

    /**
     * Structural key for a `MapNode`. `getKeyValues()` is a flat
     * `[k, v, k, v, …]` list in literal order; each entry keys on both its
     * key and value digest. Bails to `null` like {@see collectionValueKey}
     * when any key or value is not value-keyable.
     *
     * @param array<int, AbstractNode> $keyValues
     */
    private function mapValueKey(array $keyValues): ?string
    {
        $digest = '';
        for ($i = 0, $length = count($keyValues); $i < $length; $i += 2) {
            $keyChildKey = $this->valueKey($keyValues[$i]);
            $valueChildKey = $this->valueKey($keyValues[$i + 1]);
            if ($keyChildKey === null || $valueChildKey === null) {
                return null;
            }

            $digest .= $this->lengthPrefixedKey($keyChildKey) . '=>' . $this->lengthPrefixedKey($valueChildKey);
        }

        return 'map:[' . $digest . ']';
    }

    /**
     * Length-prefix a child key so concatenated digests are unambiguous.
     * A raw comma-join would let a crafted string element forge the digest
     * of a differently-shaped literal (e.g. `["a,str:b"]` vs `["a" "b"]`),
     * collapsing two distinct values onto one slot and corrupting it.
     */
    private function lengthPrefixedKey(string $childKey): string
    {
        return strlen($childKey) . ':' . $childKey;
    }
}
