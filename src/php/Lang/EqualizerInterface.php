<?php

declare(strict_types=1);

namespace Phel\Lang;

interface EqualizerInterface
{
    /**
     * @return bool True, if $a is equals $b
     */
    public function equals(mixed $a, mixed $b): bool;

    /**
     * Equality used for collection keys/elements (map, set, hashed lookup).
     *
     * Differs from {@see self::equals()} only for NaN: a NaN key matches
     * itself (Java Double.equals semantics) so it stays retrievable, while
     * scalar `=` keeps NaN unequal to itself (IEEE-754).
     *
     * @return bool True, if $a is equals $b as a collection key
     */
    public function equalsKey(mixed $a, mixed $b): bool;
}
