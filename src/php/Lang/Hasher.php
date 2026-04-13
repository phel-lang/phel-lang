<?php

declare(strict_types=1);

namespace Phel\Lang;

use Gacela\Container\Attribute\Singleton;
use RuntimeException;

use function gettype;
use function is_float;
use function is_int;
use function is_object;
use function is_string;

/**
 * This Hasher is inspired by the Clojurescript implementation.
 * These constants are the same hash value as in clojure.
 */
#[Singleton]
final class Hasher implements HasherInterface
{
    private const int NULL_HASH_VALUE = 0;

    private const int TRUE_HASH_VALUE = 1231;

    private const int FALSE_HASH_VALUE = 1237;

    private const int POSITIVE_INF_HASH_VALUE = 2_146_435_072;

    private const int NEGATIVE_INF_HASH_VALUE = -1_048_576;

    private const int DEFAULT_FLOAT_HASH_VALUE = 2_146_959_360;

    /**
     * @param mixed $value The value to hash
     *
     * @return int The hash of the given value
     */
    public function hash(mixed $value): int
    {
        // Fast paths first: ints and Phel hashable types are the dominant
        // shapes in collection workloads, so we test them before falling
        // back to the broader scalar / object branches.
        if (is_int($value)) {
            return $value;
        }

        if ($value === null) {
            return self::NULL_HASH_VALUE;
        }

        if ($value instanceof HashableInterface) {
            return $value->hash();
        }

        if (is_string($value)) {
            return crc32($value);
        }

        if ($value === true) {
            return self::TRUE_HASH_VALUE;
        }

        if ($value === false) {
            return self::FALSE_HASH_VALUE;
        }

        if (is_float($value)) {
            return $this->hashFloat($value);
        }

        if (is_object($value)) {
            return crc32(spl_object_hash($value));
        }

        throw new RuntimeException('This type is not hashable: ' . gettype($value));
    }

    private function hashFloat(float $value): int
    {
        if (is_finite($value)) {
            return (int) ($value);
        }

        if ($value === INF) {
            return self::POSITIVE_INF_HASH_VALUE;
        }

        if ($value === -INF) {
            return self::NEGATIVE_INF_HASH_VALUE;
        }

        return self::DEFAULT_FLOAT_HASH_VALUE;
    }
}
