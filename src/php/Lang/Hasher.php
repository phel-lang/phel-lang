<?php

declare(strict_types=1);

namespace Phel\Lang;

use RuntimeException;

use function gettype;
use function is_int;
use function is_object;
use function is_scalar;
use function is_string;

/**
 * This Hasher is inspired by the Clojurescript implementation.
 * These constants are the same hash value as in clojure.
 */
final class Hasher implements HasherInterface
{
    private const NULL_HASH_VALUE = 0;

    private const TRUE_HASH_VALUE = 1231;

    private const FALSE_HASH_VALUE = 1237;

    private const POSITIVE_INF_HASH_VALUE = 2_146_435_072;

    private const NEGATIVE_INF_HASH_VALUE = -1_048_576;

    private const DEFAULT_FLOAT_HASH_VALUE = 2_146_959_360;

    /**
     * @param mixed $value The value to hash
     *
     * @return int The hash of the given value
     */
    public function hash(mixed $value): int
    {
        if ($value === null) {
            return self::NULL_HASH_VALUE;
        }

        if ($value instanceof HashableInterface) {
            return $value->hash();
        }

        if (is_scalar($value)) {
            return $this->hashScalar($value);
        }

        if (is_object($value)) {
            return crc32(spl_object_hash($value));
        }

        throw new RuntimeException('This type is not hashable: ' . gettype($value));
    }

    private function hashScalar(float|bool|int|string $value): int
    {
        if ($value === true) {
            return self::TRUE_HASH_VALUE;
        }

        if ($value === false) {
            return self::FALSE_HASH_VALUE;
        }

        if (is_string($value)) {
            return crc32($value);
        }

        if (is_int($value)) {
            return $value;
        }

        /** @var float $value */
        return $this->hashFloat($value);
    }

    private function hashFloat(float $value): int
    {
        if (is_finite($value)) {
            return (int)($value);
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
