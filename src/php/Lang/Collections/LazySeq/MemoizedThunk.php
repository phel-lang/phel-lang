<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use function assert;

/**
 * A wrapper around a thunk (nullary function) that memoizes its result.
 * This ensures the thunk is only invoked once, and subsequent calls return
 * the cached result. This is critical for maintaining persistence in lazy
 * sequences - multiple calls to cdr() on the same instance must return
 * consistent results even when the thunk closes over mutable state like generators.
 */
final class MemoizedThunk
{
    /** @var callable|null The thunk to invoke (null after first invocation) */
    private $fn;

    /** @var bool Whether the thunk has been invoked */
    private bool $realized = false;

    /** @var mixed The memoized result of invoking the thunk */
    private mixed $result = null;

    /**
     * @param callable $fn The thunk to memoize
     */
    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    /**
     * Invokes the thunk if not already invoked, and returns the memoized result.
     *
     * @return mixed The result of invoking the thunk
     */
    public function invoke(): mixed
    {
        if (!$this->realized) {
            $fn = $this->fn;
            assert($fn !== null, 'Thunk must not be null before realization');
            $this->result = $fn();
            $this->realized = true;
            $this->fn = null; // Allow garbage collection
        }

        return $this->result;
    }

    /**
     * Returns whether the thunk has been realized (invoked).
     */
    public function isRealized(): bool
    {
        return $this->realized;
    }
}
