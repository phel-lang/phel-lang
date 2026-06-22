<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * Shared rolling-hash combinators used by the persistent collections.
 *
 * Every accumulator is wrapped to a 32-bit unsigned range (HASH_MASK) on each
 * step so the running value can never exceed PHP_INT_MAX and silently promote
 * to float — which, under strict_types, throws a TypeError the moment the
 * result is assigned back to an `int`/`?int` hash cache. That was the bug
 * behind #2567, originally observed with vectors of 13+ elements. 32 bits
 * matches Clojure's hash width and the range produced by {@see Hasher}
 * (crc32, float bit patterns).
 *
 * Implementations must provide `hash(mixed): int` (see {@see HasherInterface}).
 */
trait HashCombinerTrait
{
    private const int HASH_MASK = 0xFFFFFFFF;

    /**
     * Order-sensitive hash (Clojure's hashOrdered): seed 1, then
     * `31 * $hash + hash($value)` per element. Used by sequential
     * collections (vector, list, queue, lazy seq, cons).
     */
    public function orderedHash(iterable $values): int
    {
        $hash = 1;
        foreach ($values as $value) {
            $hash = (31 * $hash + $this->hash($value)) & self::HASH_MASK;
        }

        return $hash;
    }

    /**
     * Order-insensitive hash (Clojure's hashUnordered): the running sum of
     * element hashes, seeded at 0. Used by sets.
     */
    public function unorderedHash(iterable $values): int
    {
        $hash = 0;
        foreach ($values as $value) {
            $hash = ($hash + $this->hash($value)) & self::HASH_MASK;
        }

        return $hash;
    }

    /**
     * Order-insensitive hash of key/value pairs: the running sum of
     * `hash($key) ^ hash($value)`, seeded at 1. Used by maps.
     */
    public function unorderedKeyedHash(iterable $entries): int
    {
        $hash = 1;
        foreach ($entries as $key => $value) {
            $hash = ($hash + (($this->hash($key) ^ $this->hash($value)) & self::HASH_MASK)) & self::HASH_MASK;
        }

        return $hash;
    }
}
