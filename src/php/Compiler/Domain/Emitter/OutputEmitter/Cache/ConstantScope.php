<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use SplObjectStorage;

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
 */
final class ConstantScope
{
    /** @var SplObjectStorage<AbstractNode, int> */
    private SplObjectStorage $constSlots;

    /** @var SplObjectStorage<AbstractNode, int> */
    private SplObjectStorage $callSlots;

    private int $nextConstId = 0;

    private int $nextCallId = 0;

    public function __construct()
    {
        $this->constSlots = new SplObjectStorage();
        $this->callSlots = new SplObjectStorage();
    }

    public function reserve(AbstractNode $node): int
    {
        if (!$this->constSlots->offsetExists($node)) {
            $this->constSlots[$node] = $this->nextConstId++;
        }

        /** @var int $id */
        $id = $this->constSlots[$node];
        return $id;
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
}
