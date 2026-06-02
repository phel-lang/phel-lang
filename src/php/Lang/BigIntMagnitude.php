<?php

declare(strict_types=1);

namespace Phel\Lang;

use function array_pop;
use function count;
use function intdiv;
use function str_pad;
use function str_split;
use function strrev;

/**
 * Unsigned arbitrary-precision arithmetic on magnitudes: arrays of
 * base-1_000_000_000 digits, least-significant first, with no trailing zeros.
 *
 * Choosing 10^9 keeps any single product of two digits inside 64-bit signed
 * integer range with margin (10^18 < 9.22e18 = PHP_INT_MAX). These kernels are
 * pure and sign-agnostic; {@see BigInt} owns the sign, metadata, and the
 * signed semantics layered on top.
 */
final class BigIntMagnitude
{
    public const int BASE = 1_000_000_000;

    public const int BASE_DIGITS = 9;

    /**
     * Splits a non-negative PHP int into base-10^9 digits.
     *
     * @param int<0, max> $value
     *
     * @return list<int>
     */
    public static function split(int $value): array
    {
        $magnitude = [];
        while ($value > 0) {
            $magnitude[] = $value % self::BASE;
            $value = intdiv($value, self::BASE);
        }

        return $magnitude;
    }

    /**
     * Parses a non-empty decimal digit string (no sign, no leading zeros)
     * into a trimmed magnitude.
     *
     * @return list<int>
     */
    public static function fromDecimalDigits(string $digits): array
    {
        // Process digits in chunks of BASE_DIGITS from the right.
        $magnitude = [];
        $reversed = strrev($digits);
        $chunks = str_split($reversed, self::BASE_DIGITS);
        foreach ($chunks as $chunk) {
            $magnitude[] = (int) strrev($chunk);
        }

        return self::trim($magnitude);
    }

    /**
     * Renders a non-empty magnitude as its decimal string (sign-free).
     *
     * @param list<int> $magnitude
     */
    public static function toDecimalString(array $magnitude): string
    {
        $count = count($magnitude);
        $highest = $magnitude[$count - 1];
        $out = (string) $highest;
        for ($i = $count - 2; $i >= 0; --$i) {
            $out .= str_pad((string) $magnitude[$i], self::BASE_DIGITS, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     */
    public static function compare(array $a, array $b): int
    {
        $countA = count($a);
        $countB = count($b);
        if ($countA !== $countB) {
            return $countA <=> $countB;
        }

        for ($i = $countA - 1; $i >= 0; --$i) {
            if ($a[$i] !== $b[$i]) {
                return $a[$i] <=> $b[$i];
            }
        }

        return 0;
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     *
     * @return list<int>
     */
    public static function add(array $a, array $b): array
    {
        $result = [];
        $carry = 0;
        $max = max(count($a), count($b));
        for ($i = 0; $i < $max; ++$i) {
            $sum = ($a[$i] ?? 0) + ($b[$i] ?? 0) + $carry;
            $result[] = $sum % self::BASE;
            $carry = intdiv($sum, self::BASE);
        }

        if ($carry > 0) {
            $result[] = $carry;
        }

        return self::trim($result);
    }

    /**
     * Subtracts $b from $a; requires |a| >= |b|.
     *
     * @param list<int> $a
     * @param list<int> $b
     *
     * @return list<int>
     */
    public static function subtract(array $a, array $b): array
    {
        $result = [];
        $borrow = 0;
        $countA = count($a);
        for ($i = 0; $i < $countA; ++$i) {
            $diff = $a[$i] - ($b[$i] ?? 0) - $borrow;
            if ($diff < 0) {
                $diff += self::BASE;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result[] = $diff;
        }

        return self::trim($result);
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     *
     * @return list<int>
     */
    public static function multiply(array $a, array $b): array
    {
        $countA = count($a);
        $countB = count($b);
        $result = array_fill(0, $countA + $countB, 0);
        for ($i = 0; $i < $countA; ++$i) {
            $carry = 0;
            for ($j = 0; $j < $countB; ++$j) {
                $product = $result[$i + $j] + $a[$i] * $b[$j] + $carry;
                $result[$i + $j] = $product % self::BASE;
                $carry = intdiv($product, self::BASE);
            }

            if ($carry > 0) {
                $result[$i + $countB] += $carry;
            }
        }

        /** @var list<int> $result */
        return self::trim($result);
    }

    /**
     * Long division of magnitudes; returns [$quotient, $remainder].
     *
     * @param list<int> $a
     * @param list<int> $b
     *
     * @return array{0: list<int>, 1: list<int>}
     */
    public static function divMod(array $a, array $b): array
    {
        if (self::compare($a, $b) < 0) {
            return [[], $a];
        }

        // Single-digit divisor — fast path.
        if (count($b) === 1) {
            return self::divModSingle($a, $b[0]);
        }

        $countA = count($a);
        $remainder = [];
        $quotient = array_fill(0, $countA, 0);

        for ($i = $countA - 1; $i >= 0; --$i) {
            // Prepend a[i] to remainder (least-significant first means index 0).
            array_unshift($remainder, $a[$i]);
            $remainder = self::trim($remainder);

            if (self::compare($remainder, $b) < 0) {
                $quotient[$i] = 0;
                continue;
            }

            $digit = self::trialQuotientDigit($remainder, $b);
            $product = self::multiply($b, [$digit]);
            $remainder = self::subtract($remainder, $product);
            $quotient[$i] = $digit;
        }

        /** @var list<int> $quotient */
        return [self::trim($quotient), $remainder];
    }

    /**
     * Removes trailing zero digits so a magnitude has a canonical form.
     *
     * @param list<int> $magnitude
     *
     * @return list<int>
     */
    public static function trim(array $magnitude): array
    {
        while ($magnitude !== [] && $magnitude[count($magnitude) - 1] === 0) {
            array_pop($magnitude);
        }

        return $magnitude;
    }

    /**
     * Returns the largest base-digit q in [0, BASE) such that b*q <= remainder,
     * via binary search. The remainder is guaranteed >= b at call time so
     * q >= 1.
     *
     * @param list<int> $remainder
     * @param list<int> $b
     */
    private static function trialQuotientDigit(array $remainder, array $b): int
    {
        $lo = 1;
        $hi = self::BASE - 1;
        $best = 1;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $product = self::multiply($b, [$mid]);
            $cmp = self::compare($product, $remainder);
            if ($cmp <= 0) {
                $best = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $best;
    }

    /**
     * @param list<int> $a
     *
     * @return array{0: list<int>, 1: list<int>}
     */
    private static function divModSingle(array $a, int $divisor): array
    {
        $quotient = array_fill(0, count($a), 0);
        $remainder = 0;
        for ($i = count($a) - 1; $i >= 0; --$i) {
            $current = $remainder * self::BASE + $a[$i];
            $quotient[$i] = intdiv($current, $divisor);
            $remainder = $current % $divisor;
        }

        /** @var list<int> $quotient */
        return [
            self::trim($quotient),
            $remainder === 0 ? [] : [$remainder],
        ];
    }
}
