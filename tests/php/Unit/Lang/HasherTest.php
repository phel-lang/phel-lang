<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use ErrorException;
use Phel\Lang\BigInt;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function is_float;

final class HasherTest extends TestCase
{
    public function test_fractional_values_in_one_integer_interval_have_distinct_hashes(): void
    {
        $hasher = new Hasher();
        $hashes = [];
        for ($index = 0; $index < 256; ++$index) {
            $hashes[] = $hasher->hash(1.0 + $index / 256.0);
        }

        self::assertCount(256, array_unique($hashes));
    }

    public function test_large_and_tiny_finite_values_hash_without_warnings(): void
    {
        $hasher = new Hasher();
        $hashes = [];
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            foreach ([1e300, 1e301, -1e300, PHP_FLOAT_MAX, -PHP_FLOAT_MAX, PHP_FLOAT_MIN, 1e-300] as $value) {
                $hashes[] = $hasher->hash($value);
            }
        } finally {
            restore_error_handler();
        }

        self::assertCount(7, array_unique($hashes));
    }

    public function test_signed_zeros_hash_equally_and_find_the_same_map_key(): void
    {
        $hasher = new Hasher();
        $equalizer = new Equalizer();
        self::assertTrue($equalizer->equals(0.0, -0.0));
        self::assertSame($hasher->hash(0.0), $hasher->hash(-0.0));
        $map = PersistentHashMap::empty($hasher, $equalizer)->put(0.0, 'zero');

        self::assertSame('zero', $map->find(-0.0));
        self::assertCount(1, $map->put(-0.0, 'changed'));
    }

    public function test_every_nan_payload_keeps_the_existing_hash_and_map_key(): void
    {
        $hasher = new Hasher();
        $map = PersistentHashMap::empty($hasher, new Equalizer())->put(NAN, 'nan');
        foreach (['7ff8000000000000', '7ff8000000000001', '7ff0000000000001', 'fff8000000000001'] as $bits) {
            $value = $this->floatFromBits($bits);
            self::assertNan($value);
            self::assertSame(2_146_959_360, $hasher->hash($value));
            self::assertSame('nan', $map->find($value));
            self::assertCount(1, $map->put($value, 'changed'));
        }
    }

    public function test_infinite_values_keep_their_existing_hashes(): void
    {
        $hasher = new Hasher();
        self::assertSame(2_146_435_072, $hasher->hash(INF));
        self::assertSame(-1_048_576, $hasher->hash(-INF));
    }

    public function test_integer_and_bigint_hashes_keep_the_existing_equality_contract(): void
    {
        $hasher = new Hasher();
        $equalizer = new Equalizer();
        foreach ([0, 1, -1, PHP_INT_MAX, PHP_INT_MIN] as $integer) {
            $big = BigInt::fromInt($integer);
            self::assertTrue($equalizer->equals($integer, $big));
            self::assertSame($integer, $hasher->hash($integer));
            self::assertSame($hasher->hash($integer), $hasher->hash($big));
        }

        self::assertFalse($equalizer->equals(1, 1.0));
        self::assertFalse($equalizer->equals(BigInt::fromInt(1), 1.0));
    }

    private function floatFromBits(string $bits): float
    {
        $bytes = hex2bin($bits);
        if ($bytes === false) {
            throw new RuntimeException('Invalid float bits');
        }

        $decoded = unpack('Evalue', $bytes);
        $value = $decoded['value'] ?? null;
        if (!is_float($value)) {
            throw new RuntimeException('Invalid float fixture');
        }

        return $value;
    }
}
