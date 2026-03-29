<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * Deferred computation evaluated at most once and cached.
 * Matches Clojure's delay semantics.
 */
final class Delay
{
    private mixed $value = null;

    /** @var ?callable */
    private mixed $fn;

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
