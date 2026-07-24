<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * Deferred computation evaluated at most once and cached.
 */
final class Delay
{
    private mixed $value = null;

    /** @var (callable(): mixed)|null */
    private $fn;

    /**
     * @param callable(): mixed $fn nullary thunk, invoked at most once
     */
    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    public function deref(): mixed
    {
        if ($this->fn !== null) {
            $this->value = ($this->fn)();
            $this->fn = null;
        }

        return $this->value;
    }

    public function isRealized(): bool
    {
        return $this->fn === null;
    }
}
