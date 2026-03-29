<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * A lightweight mutable container for transducer state.
 * Unlike Variable, has no watches or validators.
 *
 * @template T
 */
final class Volatile
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

    /**
     * @param T $value
     *
     * @return T
     */
    public function reset(mixed $value): mixed
    {
        $this->value = $value;

        return $value;
    }
}
