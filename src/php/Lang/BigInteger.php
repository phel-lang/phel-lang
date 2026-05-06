<?php

declare(strict_types=1);

namespace Phel\Lang;

use DivisionByZeroError;
use InvalidArgumentException;
use OverflowException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Stringable;

use function array_pop;
use function count;
use function intdiv;
use function is_int;
use function preg_match;
use function sprintf;
use function str_pad;
use function str_split;
use function strlen;
use function strrev;
use function substr;

/**
 * Arbitrary-precision signed integer.
 *
 * Internal representation: sign (-1, 0, or 1) plus a magnitude expressed as
 * an array of base-1_000_000_000 digits, least-significant first. Choosing
 * 10^9 keeps any single multiplication of two digits inside 64-bit signed
 * integer range with margin (10^18 < 9.22e18 = PHP_INT_MAX).
 */
final readonly class BigInteger implements TypeInterface, Stringable
{
    private const int BASE = 1_000_000_000;

    private const int BASE_DIGITS = 9;

    /**
     * @param int       $sign      -1, 0, or 1; must be 0 iff magnitude is empty
     * @param list<int> $magnitude base-10^9 digits, least-significant first; no trailing zeros
     */
    private function __construct(
        private int $sign,
        private array $magnitude,
        private ?PersistentMapInterface $meta = null,
        private ?SourceLocation $startLocation = null,
        private ?SourceLocation $endLocation = null,
    ) {}

    public function __toString(): string
    {
        if ($this->sign === 0) {
            return '0';
        }

        $count = count($this->magnitude);
        $highest = $this->magnitude[$count - 1];
        $out = (string) $highest;
        for ($i = $count - 2; $i >= 0; --$i) {
            $out .= str_pad((string) $this->magnitude[$i], self::BASE_DIGITS, '0', STR_PAD_LEFT);
        }

        return $this->sign < 0 ? '-' . $out : $out;
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->sign, $this->magnitude, $meta, $this->startLocation, $this->endLocation);
    }

    public function setStartLocation(?SourceLocation $startLocation): static
    {
        return new self($this->sign, $this->magnitude, $this->meta, $startLocation, $this->endLocation);
    }

    public function setEndLocation(?SourceLocation $endLocation): static
    {
        return new self($this->sign, $this->magnitude, $this->meta, $this->startLocation, $endLocation);
    }

    public function getStartLocation(): ?SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): ?SourceLocation
    {
        return $this->endLocation;
    }

    public function copyLocationFrom(mixed $other): static
    {
        if ($other instanceof SourceLocationInterface) {
            return $this
                ->setStartLocation($other->getStartLocation())
                ->setEndLocation($other->getEndLocation());
        }

        return $this;
    }

    public static function zero(): self
    {
        return new self(0, []);
    }

    public static function one(): self
    {
        return new self(1, [1]);
    }

    public static function fromInt(int $value): self
    {
        if ($value === 0) {
            return self::zero();
        }

        if ($value > 0) {
            return new self(1, self::splitNonNegative($value));
        }

        // Handle PHP_INT_MIN specially: negation overflows in PHP int.
        if ($value === PHP_INT_MIN) {
            // |PHP_INT_MIN| = PHP_INT_MAX + 1; build it digit-piecewise.
            $magnitude = self::splitNonNegative(PHP_INT_MAX);
            return new self(-1, self::trim(self::addMagnitudes($magnitude, [1])));
        }

        return new self(-1, self::splitNonNegative(-$value));
    }

    /**
     * Converts a finite float to a BigInteger via its shortest round-trip
     * decimal representation, truncating fractional digits toward zero.
     * Floats too large to represent every digit (e.g. `PHP_FLOAT_MAX`)
     * round-trip through ~17 significant digits and pad with trailing
     * zeros, so equality with the literal printed by the same float
     * holds. Throws on `NaN`/`Inf`.
     */
    public static function fromFloat(float $value): self
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException(
                sprintf("Cannot convert non-finite float '%s' to BigInteger", $value),
            );
        }

        if ($value == 0.0) {
            return self::zero();
        }

        return self::fromString(self::floatToIntegerString($value));
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^-?(0|[1-9]\d*)$/', $value) !== 1) {
            throw new InvalidArgumentException(
                sprintf("Invalid BigInteger string: '%s'", $value),
            );
        }

        $isNegative = $value[0] === '-';
        $digits = $isNegative ? substr($value, 1) : $value;

        if ($digits === '0') {
            return self::zero();
        }

        $magnitude = self::digitsToMagnitude($digits);

        return new self($isNegative ? -1 : 1, $magnitude);
    }

    public function add(self $other): self
    {
        if ($this->sign === 0) {
            return $other;
        }

        if ($other->sign === 0) {
            return $this;
        }

        if ($this->sign === $other->sign) {
            return new self(
                $this->sign,
                self::addMagnitudes($this->magnitude, $other->magnitude),
            );
        }

        $cmp = $this->compareMagnitudes($this->magnitude, $other->magnitude);
        if ($cmp === 0) {
            return self::zero();
        }

        if ($cmp > 0) {
            return new self(
                $this->sign,
                $this->subtractMagnitudes($this->magnitude, $other->magnitude),
            );
        }

        return new self(
            $other->sign,
            $this->subtractMagnitudes($other->magnitude, $this->magnitude),
        );
    }

    public function subtract(self $other): self
    {
        return $this->add($other->negate());
    }

    public function multiply(self $other): self
    {
        if ($this->sign === 0 || $other->sign === 0) {
            return self::zero();
        }

        return new self(
            $this->sign * $other->sign,
            $this->multiplyMagnitudes($this->magnitude, $other->magnitude),
        );
    }

    public function divide(self $other): self
    {
        if ($other->sign === 0) {
            throw new DivisionByZeroError('Division by zero');
        }

        if ($this->sign === 0) {
            return self::zero();
        }

        [$quotient] = $this->divModMagnitudes($this->magnitude, $other->magnitude);
        if ($quotient === []) {
            return self::zero();
        }

        return new self($this->sign * $other->sign, $quotient);
    }

    public function mod(self $other): self
    {
        if ($other->sign === 0) {
            throw new DivisionByZeroError('Modulo by zero');
        }

        if ($this->sign === 0) {
            return self::zero();
        }

        [, $remainder] = $this->divModMagnitudes($this->magnitude, $other->magnitude);
        if ($remainder === []) {
            return self::zero();
        }

        return new self($this->sign, $remainder);
    }

    public function gcd(self $other): self
    {
        $a = $this->abs();
        $b = $other->abs();

        while (!$b->isZero()) {
            $temp = $b;
            $b = $a->mod($b);
            $a = $temp;
        }

        return $a;
    }

    public function negate(): self
    {
        if ($this->sign === 0) {
            return $this;
        }

        return new self(-$this->sign, $this->magnitude);
    }

    public function abs(): self
    {
        if ($this->sign >= 0) {
            return $this;
        }

        return new self(1, $this->magnitude);
    }

    public function pow(int $exp): self
    {
        if ($exp < 0) {
            throw new InvalidArgumentException('Exponent must be non-negative');
        }

        if ($exp === 0) {
            return self::one();
        }

        $base = $this;
        $result = self::one();
        while ($exp > 0) {
            if (($exp & 1) === 1) {
                $result = $result->multiply($base);
            }

            $exp >>= 1;
            if ($exp > 0) {
                $base = $base->multiply($base);
            }
        }

        return $result;
    }

    public function compareTo(self $other): int
    {
        if ($this->sign !== $other->sign) {
            return $this->sign <=> $other->sign;
        }

        if ($this->sign === 0) {
            return 0;
        }

        $magCmp = $this->compareMagnitudes($this->magnitude, $other->magnitude);

        return $this->sign > 0 ? $magCmp : -$magCmp;
    }

    public function equals(mixed $other): bool
    {
        if ($other instanceof self) {
            return $this->compareTo($other) === 0;
        }

        if (is_int($other)) {
            return $this->compareTo(self::fromInt($other)) === 0;
        }

        return false;
    }

    public function hash(): int
    {
        if ($this->fitsInPhpInt()) {
            return $this->toInt();
        }

        return crc32((string) $this);
    }

    public function signum(): int
    {
        return $this->sign;
    }

    public function isZero(): bool
    {
        return $this->sign === 0;
    }

    public function isOne(): bool
    {
        return $this->sign === 1
            && count($this->magnitude) === 1
            && $this->magnitude[0] === 1;
    }

    public function fitsInPhpInt(): bool
    {
        if ($this->sign === 0) {
            return true;
        }

        if ($this->sign > 0) {
            return $this->compareMagnitudes($this->magnitude, $this->phpIntMaxMagnitude()) <= 0;
        }

        return $this->compareMagnitudes($this->magnitude, $this->phpIntMinAbsMagnitude()) <= 0;
    }

    public function toInt(): int
    {
        if (!$this->fitsInPhpInt()) {
            throw new OverflowException(
                sprintf("BigInteger value '%s' exceeds PHP int range", $this),
            );
        }

        if ($this->sign === 0) {
            return 0;
        }

        // Materialise from base-10^9 digits without overflow.
        if ($this->sign < 0
            && $this->compareMagnitudes($this->magnitude, $this->phpIntMinAbsMagnitude()) === 0
        ) {
            return PHP_INT_MIN;
        }

        $value = 0;
        for ($i = count($this->magnitude) - 1; $i >= 0; --$i) {
            $value = $value * self::BASE + $this->magnitude[$i];
        }

        return $this->sign > 0 ? $value : -$value;
    }

    /**
     * @param int<0, max> $value
     *
     * @return list<int>
     */
    private static function splitNonNegative(int $value): array
    {
        $magnitude = [];
        while ($value > 0) {
            $magnitude[] = $value % self::BASE;
            $value = intdiv($value, self::BASE);
        }

        return $magnitude;
    }

    /**
     * @return list<int>
     */
    private static function digitsToMagnitude(string $digits): array
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
     * @param list<int> $a
     * @param list<int> $b
     */
    private function compareMagnitudes(array $a, array $b): int
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
    private static function addMagnitudes(array $a, array $b): array
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
    private function subtractMagnitudes(array $a, array $b): array
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
    private function multiplyMagnitudes(array $a, array $b): array
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
    private function divModMagnitudes(array $a, array $b): array
    {
        if ($this->compareMagnitudes($a, $b) < 0) {
            return [[], $a];
        }

        // Single-digit divisor — fast path.
        if (count($b) === 1) {
            return $this->divModSingle($a, $b[0]);
        }

        $countA = count($a);
        $remainder = [];
        $quotient = array_fill(0, $countA, 0);

        for ($i = $countA - 1; $i >= 0; --$i) {
            // Prepend a[i] to remainder (least-significant first means index 0).
            array_unshift($remainder, $a[$i]);
            $remainder = self::trim($remainder);

            if ($this->compareMagnitudes($remainder, $b) < 0) {
                $quotient[$i] = 0;
                continue;
            }

            $digit = $this->trialQuotientDigit($remainder, $b);
            $product = $this->multiplyMagnitudes($b, [$digit]);
            $remainder = $this->subtractMagnitudes($remainder, $product);
            $quotient[$i] = $digit;
        }

        /** @var list<int> $quotient */
        return [self::trim($quotient), $remainder];
    }

    /**
     * Returns the largest base-digit q in [0, BASE) such that b*q <= remainder,
     * via binary search. The remainder is guaranteed >= b at call time so
     * q >= 1.
     *
     * @param list<int> $remainder
     * @param list<int> $b
     */
    private function trialQuotientDigit(array $remainder, array $b): int
    {
        $lo = 1;
        $hi = self::BASE - 1;
        $best = 1;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $product = $this->multiplyMagnitudes($b, [$mid]);
            $cmp = $this->compareMagnitudes($product, $remainder);
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
    private function divModSingle(array $a, int $divisor): array
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

    /**
     * @param list<int> $magnitude
     *
     * @return list<int>
     */
    private static function trim(array $magnitude): array
    {
        while ($magnitude !== [] && $magnitude[count($magnitude) - 1] === 0) {
            array_pop($magnitude);
        }

        return $magnitude;
    }

    /**
     * @return list<int>
     */
    private function phpIntMaxMagnitude(): array
    {
        /** @var list<int>|null $cached */
        static $cached = null;
        return $cached ??= self::splitNonNegative(PHP_INT_MAX);
    }

    /**
     * @return list<int>
     */
    private function phpIntMinAbsMagnitude(): array
    {
        /** @var list<int>|null $cached */
        static $cached = null;
        return $cached ??= self::trim(self::addMagnitudes($this->phpIntMaxMagnitude(), [1]));
    }

    /**
     * Renders `$value` as the shortest %g-formatted decimal that round-trips
     * back to the same float, then expands scientific notation and truncates
     * the fractional digits toward zero so the result is a plain integer
     * string suitable for {@see self::fromString()}.
     */
    private static function floatToIntegerString(float $value): string
    {
        $repr = self::shortestRoundTripDecimal($value);
        if (preg_match('/^(-?)(\d+)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $repr, $matches) !== 1) {
            throw new InvalidArgumentException(
                sprintf("Unrecognised float string: '%s'", $repr),
            );
        }

        $sign = $matches[1];
        $intPart = $matches[2];
        $fracPart = $matches[3] ?? '';
        $exponent = (int) ($matches[4] ?? '0');

        $digits = $intPart . $fracPart;
        $pointPosition = strlen($intPart) + $exponent;

        if ($pointPosition <= 0) {
            return '0';
        }

        $intStr = $pointPosition >= strlen($digits)
            ? $digits . str_repeat('0', $pointPosition - strlen($digits))
            : substr($digits, 0, $pointPosition);

        $intStr = ltrim($intStr, '0');
        if ($intStr === '') {
            return '0';
        }

        return $sign . $intStr;
    }

    private static function shortestRoundTripDecimal(float $value): string
    {
        for ($precision = 1; $precision < 17; ++$precision) {
            $repr = sprintf('%.' . $precision . 'g', $value);
            if ((float) $repr === $value) {
                return $repr;
            }
        }

        return sprintf('%.17g', $value);
    }
}
