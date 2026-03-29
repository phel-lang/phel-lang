<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * Wraps a value to signal early termination from reduce/transduce.
 *
 * @template T
 */
final readonly class Reduced
{
    /**
     * @param T $value
     */
    public function __construct(
        private mixed $value,
    ) {}

    /**
     * @return T
     */
    public function deref(): mixed
    {
        return $this->value;
    }
}
