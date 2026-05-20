<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use SplObjectStorage;

/**
 * Per-fn-body scope tracking which pure collection literals will be
 * hoisted to a `static` PHP variable. Each registered node is assigned a
 * monotonically increasing slot id; the emitter renders the literal as
 * `($__phel_const_<id> ??= ...)` and declares matching `static` lines at
 * the top of the body.
 */
final class ConstantScope
{
    /** @var SplObjectStorage<AbstractNode, int> */
    private SplObjectStorage $slots;

    private int $nextId = 0;

    public function __construct()
    {
        $this->slots = new SplObjectStorage();
    }

    public function reserve(AbstractNode $node): int
    {
        if (!$this->slots->offsetExists($node)) {
            $this->slots[$node] = $this->nextId++;
        }

        /** @var int $id */
        $id = $this->slots[$node];
        return $id;
    }

    public function lookup(AbstractNode $node): ?int
    {
        if (!$this->slots->offsetExists($node)) {
            return null;
        }

        /** @var int $id */
        $id = $this->slots[$node];
        return $id;
    }

    public function count(): int
    {
        return $this->nextId;
    }
}
