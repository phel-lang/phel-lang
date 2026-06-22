<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

/**
 * Shared "flush once at shutdown" bookkeeping for the on-disk caches. Both
 * {@see CompiledCodeCache} and {@see PhpScanIndexCache} only mutate their
 * in-memory index per `put`/`invalidate` and defer the disk write to a single
 * `register_shutdown_function` flush, turning a cold build's index I/O from
 * O(N²) into O(N). This keeps the dirty-flag + lazy-registration pair in one
 * place; each cache still owns its own {@see save()} body.
 */
trait DeferredFlushTrait
{
    private bool $flushPending = false;

    private bool $shutdownRegistered = false;

    /**
     * Each cache flushes its in-memory mutations to disk. Called exactly once
     * per process at shutdown (and may also be called explicitly, e.g. by
     * tests needing cross-instance persistence in the same process).
     */
    abstract public function save(): void;

    /**
     * Mark the in-memory state as needing a flush and ensure {@see save()}
     * runs once at shutdown. Registration is lazy (not in the constructor) so
     * a read-only process never installs a handler.
     */
    private function markFlushPending(): void
    {
        $this->flushPending = true;
        if ($this->shutdownRegistered) {
            return;
        }

        register_shutdown_function([$this, 'save']);
        $this->shutdownRegistered = true;
    }

    private function isFlushPending(): bool
    {
        return $this->flushPending;
    }

    private function clearFlushPending(): void
    {
        $this->flushPending = false;
    }
}
