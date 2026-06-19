<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\BigInt;
use Phel\Lang\Equalizer;
use Phel\Lang\Ratio;
use PHPUnit\Framework\TestCase;

final class EqualizerTest extends TestCase
{
    private Equalizer $equalizer;

    protected function setUp(): void
    {
        $this->equalizer = new Equalizer();
    }

    public function test_it_returns_true_for_identical_values(): void
    {
        self::assertTrue($this->equalizer->equals(1, 1));
        self::assertTrue($this->equalizer->equals('hello', 'hello'));
        self::assertTrue($this->equalizer->equals(null, null));
    }

    public function test_it_returns_false_for_different_native_values(): void
    {
        self::assertFalse($this->equalizer->equals(1, 2));
        self::assertFalse($this->equalizer->equals(1, '1'));
    }

    public function test_it_compares_bigint_and_int_symmetrically(): void
    {
        $big = BigInt::fromInt(5);

        self::assertTrue($this->equalizer->equals($big, 5));
        self::assertTrue($this->equalizer->equals(5, $big));
    }

    public function test_it_returns_false_for_bigint_and_unequal_int(): void
    {
        $big = BigInt::fromInt(5);

        self::assertFalse($this->equalizer->equals($big, 6));
        self::assertFalse($this->equalizer->equals(6, $big));
    }

    public function test_it_compares_two_bigints(): void
    {
        $a = BigInt::fromString('12345678901234567890');
        $b = BigInt::fromString('12345678901234567890');
        $c = BigInt::fromString('12345678901234567891');

        self::assertTrue($this->equalizer->equals($a, $b));
        self::assertFalse($this->equalizer->equals($a, $c));
    }

    public function test_it_returns_false_for_int_and_float_with_same_value(): void
    {
        // Different categories: integers and floats are not equal under `=`.
        self::assertFalse($this->equalizer->equals(1, 1.0));
        self::assertFalse($this->equalizer->equals(1.0, 1));
    }

    public function test_it_returns_false_for_bigint_and_float(): void
    {
        $big = BigInt::fromInt(5);

        self::assertFalse($this->equalizer->equals($big, 5.0));
        self::assertFalse($this->equalizer->equals(5.0, $big));
    }

    public function test_it_returns_false_for_rational_and_int(): void
    {
        // Ratio instances are by construction non-integral.
        $half = Ratio::create(1, 2);

        self::assertFalse($this->equalizer->equals($half, 1));
        self::assertFalse($this->equalizer->equals(1, $half));
    }

    public function test_it_returns_false_for_rational_and_bigint(): void
    {
        $half = Ratio::create(1, 2);
        $big = BigInt::fromInt(1);

        self::assertFalse($this->equalizer->equals($half, $big));
        self::assertFalse($this->equalizer->equals($big, $half));
    }

    public function test_it_compares_two_rationals(): void
    {
        $half = Ratio::create(1, 2);
        $sameHalf = Ratio::create(2, 4);
        $third = Ratio::create(1, 3);

        self::assertTrue($this->equalizer->equals($half, $sameHalf));
        self::assertTrue($this->equalizer->equals($sameHalf, $half));
        self::assertFalse($this->equalizer->equals($half, $third));
    }

    public function test_scalar_equals_keeps_nan_unequal(): void
    {
        // Scalar `=` follows IEEE-754: NaN is never equal to itself.
        self::assertFalse($this->equalizer->equals(NAN, NAN));
    }

    public function test_equals_key_treats_nan_as_equal(): void
    {
        // Collection key equality follows Java Double.equals: NaN matches
        // itself so it can serve as a stable map/set key.
        self::assertTrue($this->equalizer->equalsKey(NAN, NAN));
    }

    public function test_equals_key_matches_equals_for_non_nan(): void
    {
        self::assertTrue($this->equalizer->equalsKey(1, 1));
        self::assertFalse($this->equalizer->equalsKey(1, 2));
        self::assertFalse($this->equalizer->equalsKey(NAN, 1.0));
        self::assertFalse($this->equalizer->equalsKey(1.0, NAN));
    }
}
