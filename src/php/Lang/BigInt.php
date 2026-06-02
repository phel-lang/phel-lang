<?php

declare(strict_types=1);

namespace Phel\Lang;

use DivisionByZeroError;
use InvalidArgumentException;
use OverflowException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Stringable;

use function count;
use function is_int;
use function preg_match;
use function sprintf;
use function strlen;
use function substr;

/**
 * Arbitrary-precision signed integer.
 *
 * Internal representation: sign (-1, 0, or 1) plus a magnitude expressed as
 * an array of base-1_000_000_000 digits, least-significant first. The
 * sign-agnostic digit arithmetic lives in {@see BigIntMagnitude}; this class
 * owns the sign, metadata, source locations, and the signed semantics.
 */
final readonly class BigInt implements TypeInterface, Stringable
{
    /**
     * @param int                                       $sign      -1, 0, or 1; must be 0 iff magnitude is empty
     * @param list<int>                                 $magnitude base-10^9 digits, least-significant first; no trailing zeros
     * @param PersistentMapInterface<mixed, mixed>|null $meta
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

        $out = BigIntMagnitude::toDecimalString($this->magnitude);

        return $this->sign < 0 ? '-' . $out : $out;
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
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
            return new self(1, BigIntMagnitude::split($value));
        }

        // Handle PHP_INT_MIN specially: negation overflows in PHP int.
        if ($value === PHP_INT_MIN) {
            // |PHP_INT_MIN| = PHP_INT_MAX + 1; build it digit-piecewise.
            $magnitude = BigIntMagnitude::split(PHP_INT_MAX);
            return new self(-1, BigIntMagnitude::trim(BigIntMagnitude::add($magnitude, [1])));
        }

        return new self(-1, BigIntMagnitude::split(-$value));
    }

    /**
     * Converts a finite float to a BigInt via its shortest round-trip
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
                sprintf("Cannot convert non-finite float '%s' to BigInt", self::describeNonFinite($value)),
            );
        }

        if ($value === 0.0) {
            return self::zero();
        }

        return self::fromString(self::floatToIntegerString($value));
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^-?(0|[1-9]\d*)$/', $value) !== 1) {
            throw new InvalidArgumentException(
                sprintf("Invalid BigInt string: '%s'", $value),
            );
        }

        $isNegative = $value[0] === '-';
        $digits = $isNegative ? substr($value, 1) : $value;

        if ($digits === '0') {
            return self::zero();
        }

        $magnitude = BigIntMagnitude::fromDecimalDigits($digits);

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
                BigIntMagnitude::add($this->magnitude, $other->magnitude),
            );
        }

        $cmp = BigIntMagnitude::compare($this->magnitude, $other->magnitude);
        if ($cmp === 0) {
            return self::zero();
        }

        if ($cmp > 0) {
            return new self(
                $this->sign,
                BigIntMagnitude::subtract($this->magnitude, $other->magnitude),
            );
        }

        return new self(
            $other->sign,
            BigIntMagnitude::subtract($other->magnitude, $this->magnitude),
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
            BigIntMagnitude::multiply($this->magnitude, $other->magnitude),
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

        [$quotient] = BigIntMagnitude::divMod($this->magnitude, $other->magnitude);
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

        [, $remainder] = BigIntMagnitude::divMod($this->magnitude, $other->magnitude);
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

        $magCmp = BigIntMagnitude::compare($this->magnitude, $other->magnitude);

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
            return BigIntMagnitude::compare($this->magnitude, $this->phpIntMaxMagnitude()) <= 0;
        }

        return BigIntMagnitude::compare($this->magnitude, $this->phpIntMinAbsMagnitude()) <= 0;
    }

    public function toInt(): int
    {
        if (!$this->fitsInPhpInt()) {
            throw new OverflowException(
                sprintf("BigInt value '%s' exceeds PHP int range", $this),
            );
        }

        if ($this->sign === 0) {
            return 0;
        }

        // Materialise from base-10^9 digits without overflow.
        if ($this->sign < 0
            && BigIntMagnitude::compare($this->magnitude, $this->phpIntMinAbsMagnitude()) === 0
        ) {
            return PHP_INT_MIN;
        }

        $value = 0;
        for ($i = count($this->magnitude) - 1; $i >= 0; --$i) {
            $value = $value * BigIntMagnitude::BASE + $this->magnitude[$i];
        }

        return $this->sign > 0 ? $value : -$value;
    }

    /**
     * @return list<int>
     */
    private function phpIntMaxMagnitude(): array
    {
        /** @var list<int>|null $cached */
        static $cached = null;
        return $cached ??= BigIntMagnitude::split(PHP_INT_MAX);
    }

    /**
     * @return list<int>
     */
    private function phpIntMinAbsMagnitude(): array
    {
        /** @var list<int>|null $cached */
        static $cached = null;
        return $cached ??= BigIntMagnitude::trim(BigIntMagnitude::add($this->phpIntMaxMagnitude(), [1]));
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

    private static function describeNonFinite(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }

        return $value > 0 ? 'Infinity' : '-Infinity';
    }
}
