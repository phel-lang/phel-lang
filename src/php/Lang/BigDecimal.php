<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArithmeticError;
use InvalidArgumentException;
use OverflowException;
use Phel\Lang\Collections\Map\PersistentMapInterface;

use Stringable;

use function crc32;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Arbitrary-precision signed decimal number.
 *
 * Internal representation: an unscaled `BigInteger` mantissa plus a
 * non-negative `scale` indicating the number of digits to the right
 * of the decimal point. The numeric value is `mantissa * 10^(-scale)`.
 *
 * Values are not auto-reduced: `1.20M` and `1.2M` are distinct values
 * (different scales) even though they compare equal.
 */
final readonly class BigDecimal implements TypeInterface, Stringable
{
    private const int MAX_DIVIDE_SCALE = 100;

    private function __construct(
        private BigInteger $mantissa,
        private int $scale,
        private ?PersistentMapInterface $meta = null,
        private ?SourceLocation $startLocation = null,
        private ?SourceLocation $endLocation = null,
    ) {}

    public function __toString(): string
    {
        return $this->renderDigits();
    }

    /**
     * Parses a canonical decimal literal. Accepts an optional sign, an
     * integer part, an optional fractional part, and an optional `eE`
     * exponent: e.g. `123`, `-123.45`, `1.5e10`, `1E-3`. Underscores in
     * numeric groups are accepted.
     */
    public static function fromString(string $value): self
    {
        $cleaned = str_replace('_', '', $value);
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/', $cleaned, $m) !== 1) {
            throw new InvalidArgumentException(
                sprintf("Invalid BigDecimal string: '%s'", $value),
            );
        }

        $sign = $m[1] === '-' ? '-' : '';
        $intPart = ltrim($m[2], '0');
        $intPart = $intPart === '' ? '0' : $intPart;

        $fracPart = $m[3] ?? '';
        $exp = isset($m[4]) ? (int) $m[4] : 0;

        $digits = $intPart . $fracPart;
        $scale = strlen($fracPart) - $exp;

        if ($scale < 0) {
            $digits .= str_repeat('0', -$scale);
            $scale = 0;
        }

        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;

        $mantissa = BigInteger::fromString($sign . $digits);

        return new self($mantissa, $scale);
    }

    public static function fromInt(int $value): self
    {
        return new self(BigInteger::fromInt($value), 0);
    }

    public static function fromBigInteger(BigInteger $value): self
    {
        return new self($value, 0);
    }

    /**
     * Builds a `BigDecimal` from a finite float using the shortest
     * round-trip decimal representation. Throws on `NaN`/`Inf`.
     */
    public static function fromFloat(float $value): self
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException(
                sprintf("Cannot convert non-finite float '%s' to BigDecimal", $value),
            );
        }

        for ($precision = 1; $precision < 17; ++$precision) {
            $repr = sprintf('%.' . $precision . 'g', $value);
            if ((float) $repr === $value) {
                return self::fromString($repr);
            }
        }

        return self::fromString(sprintf('%.17g', $value));
    }

    public function mantissa(): BigInteger
    {
        return $this->mantissa;
    }

    public function scale(): int
    {
        return $this->scale;
    }

    public function negate(): self
    {
        return new self($this->mantissa->negate(), $this->scale);
    }

    public function abs(): self
    {
        return new self($this->mantissa->abs(), $this->scale);
    }

    public function add(self $other): self
    {
        [$leftMantissa, $rightMantissa, $scale] = $this->align($other);

        return new self($leftMantissa->add($rightMantissa), $scale);
    }

    public function subtract(self $other): self
    {
        [$leftMantissa, $rightMantissa, $scale] = $this->align($other);

        return new self($leftMantissa->subtract($rightMantissa), $scale);
    }

    public function multiply(self $other): self
    {
        return new self(
            $this->mantissa->multiply($other->mantissa),
            $this->scale + $other->scale,
        );
    }

    /**
     * Exact decimal division. Extends the scale by appending zeros to the
     * dividend until the remainder is zero, up to
     * {@see self::MAX_DIVIDE_SCALE} digits past the natural alignment;
     * throws when the expansion does not terminate.
     */
    public function divideExact(self $other): self
    {
        if ($other->mantissa->isZero()) {
            throw new ArithmeticError('Division by zero');
        }

        $ten = BigInteger::fromInt(10);
        $resultScale = $this->scale - $other->scale;
        $dividend = $this->mantissa;
        $divisor = $other->mantissa;

        if ($resultScale < 0) {
            $dividend = $dividend->multiply($ten->pow(-$resultScale));
            $resultScale = 0;
        }

        $extraScale = 0;
        while (true) {
            $quotient = $dividend->divide($divisor);
            $remainder = $dividend->subtract($quotient->multiply($divisor));
            if ($remainder->isZero()) {
                return new self($quotient, $resultScale + $extraScale);
            }

            if ($extraScale >= self::MAX_DIVIDE_SCALE) {
                throw new ArithmeticError('Non-terminating decimal expansion; supply a precision');
            }

            $dividend = $dividend->multiply($ten);
            ++$extraScale;
        }
    }

    public function compareTo(self $other): int
    {
        [$leftMantissa, $rightMantissa] = $this->align($other);

        return $leftMantissa->compareTo($rightMantissa);
    }

    public function isZero(): bool
    {
        return $this->mantissa->isZero();
    }

    /**
     * Sign of this value: -1, 0, or 1.
     */
    public function signum(): int
    {
        if ($this->mantissa->isZero()) {
            return 0;
        }

        return $this->mantissa->compareTo(BigInteger::fromInt(0));
    }

    /**
     * Truncates toward zero and returns a PHP int. Throws when the
     * truncated integer part does not fit in `PHP_INT_MIN..PHP_INT_MAX`.
     */
    public function toInt(): int
    {
        $integerPart = $this->scale === 0
            ? $this->mantissa
            : $this->mantissa->divide(BigInteger::fromInt(10)->pow($this->scale));

        if (!$integerPart->fitsInPhpInt()) {
            throw new OverflowException(
                sprintf('BigDecimal value %s does not fit in PHP int range', $this),
            );
        }

        return $integerPart->toInt();
    }

    /**
     * Returns the closest IEEE-754 double to this value. Subject to the
     * usual float precision limits for very large or very small magnitudes.
     */
    public function toFloat(): float
    {
        return (float) $this->renderDigits();
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $this->compareTo($other) === 0;
    }

    public function hash(): int
    {
        return crc32($this->toCanonicalString());
    }

    public function toPlainString(): string
    {
        return $this->renderDigits();
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->mantissa, $this->scale, $meta, $this->startLocation, $this->endLocation);
    }

    public function setStartLocation(?SourceLocation $startLocation): static
    {
        return new self($this->mantissa, $this->scale, $this->meta, $startLocation, $this->endLocation);
    }

    public function setEndLocation(?SourceLocation $endLocation): static
    {
        return new self($this->mantissa, $this->scale, $this->meta, $this->startLocation, $endLocation);
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

    private function toCanonicalString(): string
    {
        $rendered = $this->renderDigits();
        if (str_contains($rendered, '.')) {
            $rendered = rtrim(rtrim($rendered, '0'), '.');
        }

        return $rendered === '' ? '0' : $rendered;
    }

    private function renderDigits(): string
    {
        $digits = (string) $this->mantissa;
        $sign = '';
        if ($digits[0] === '-') {
            $sign = '-';
            $digits = substr($digits, 1);
        }

        if ($this->scale === 0) {
            return $sign . $digits;
        }

        if (strlen($digits) <= $this->scale) {
            $digits = str_pad($digits, $this->scale + 1, '0', STR_PAD_LEFT);
        }

        $cut = strlen($digits) - $this->scale;

        return $sign . substr($digits, 0, $cut) . '.' . substr($digits, $cut);
    }

    /**
     * Aligns the two mantissas to a common scale, returning
     * `[leftMantissa, rightMantissa, sharedScale]`.
     *
     * @return array{0: BigInteger, 1: BigInteger, 2: int}
     */
    private function align(self $other): array
    {
        if ($this->scale === $other->scale) {
            return [$this->mantissa, $other->mantissa, $this->scale];
        }

        if ($this->scale > $other->scale) {
            $factor = BigInteger::fromInt(10)->pow($this->scale - $other->scale);
            return [$this->mantissa, $other->mantissa->multiply($factor), $this->scale];
        }

        $factor = BigInteger::fromInt(10)->pow($other->scale - $this->scale);
        return [$this->mantissa->multiply($factor), $other->mantissa, $other->scale];
    }
}
