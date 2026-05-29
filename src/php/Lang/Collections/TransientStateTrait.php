<?php

declare(strict_types=1);

namespace Phel\Lang\Collections;

use RuntimeException;

/**
 * Tracks whether a transient collection is still usable.
 *
 * A transient is invalidated by `persistent()`: after that call any further
 * mutation (or a second `persistent()`) throws, matching Clojure's
 * transient/persistent! contract and preventing accidental reuse of a
 * collection whose structure has been handed off to a persistent value.
 */
trait TransientStateTrait
{
    private bool $transientActive = true;

    /**
     * Guards a mutating operation. Call at the top of every mutator.
     */
    private function ensureTransientActive(): void
    {
        if (!$this->transientActive) {
            throw new RuntimeException('Transient used after persistent! call');
        }
    }

    /**
     * Marks the transient as consumed. Call inside `persistent()` before
     * building the persistent value; a second call therefore throws.
     */
    private function invalidateTransient(): void
    {
        $this->ensureTransientActive();
        $this->transientActive = false;
    }
}
