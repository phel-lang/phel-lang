<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Lang\Keyword;
use SplObjectStorage;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;
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
 * Constant slots dedup cacheable scalar/keyword literals **by value**: since
 * keywords are interned singletons (and scalars immutable), every occurrence
 * of the same value collapses to a single shared slot — one `??=` guard and
 * one `use (&...)` capture entry. Collection literals stay identity-keyed
 * (their construction is not a cheap interned singleton). The per-node map
 * still points every collapsed node at the shared slot, so the emitter
 * resolves slots by node as before and never references an uncaptured slot.
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
            // Cacheable scalar/keyword literal: dedup by value. Reuse the
            // shared slot if this value was already reserved, otherwise
            // allocate one. Either way the per-node map records the shared
            // slot so `lookup($node)` resolves every occurrence to it and the
            // emitter never references an uncaptured slot.
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
     * Stable value key for a cacheable scalar/keyword literal, or `null` for
     * any node that must stay identity-keyed (collections, symbols, numeric
     * value types, etc.). Type-tagged so `1` (int) and `"1"` (string) never
     * collide, and keywords never collide with a same-named string.
     */
    private function valueKey(AbstractNode $node): ?string
    {
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
            // `var_export` round-trips a float deterministically (NAN/INF
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
}
