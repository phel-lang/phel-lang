<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * Deferred computation evaluated at most once and cached.
 */
final class Delay
{
    private mixed $value = null;

    /** @var callable|null */
    private $fn;

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
