<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * A lightweight mutable container for transducer state.
 * Unlike Atom, has no watches or validators.
 *
 * Prefer Volatile over Atom when you need plain mutation without observation,
 * e.g. transducer accumulators or internal compiler state. It is unobservable
 * and therefore cheaper; reach for Atom only when watches/validation matter.
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
