<?php

declare(strict_types=1);

namespace Phel\Lang;

use DivisionByZeroError;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Stringable;

use function is_int;
use function sprintf;

/**
 * Rational number n/d held in canonical form: denominator > 0 and
 * gcd(|numerator|, denominator) = 1.
 *
 * Construction goes through {@see self::create()}, which auto-collapses
 * integral results to native PHP int (or {@see BigInteger} if outside the
 * PHP int range). As a consequence `Rational::create(4, 2)` returns
 * `int 2`, never a `Rational`.
 */
final readonly class Rational implements Stringable, TypeInterface
{
    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function __construct(
        private BigInteger $numerator,
        private BigInteger $denominator,
        private ?PersistentMapInterface $meta = null,
        private ?SourceLocation $startLocation = null,
        private ?SourceLocation $endLocation = null,
    ) {}

    public function __toString(): string
    {
        return sprintf('%s/%s', $this->numerator, $this->denominator);
    }

    /**
     * Builds a normalised rational. Returns native int / BigInteger when
     * the result is an integer (denominator collapses to 1), otherwise a
     * Rational. Throws on a zero denominator.
     */
    public static function create(
        BigInteger|int $numerator,
        BigInteger|int $denominator,
    ): self|BigInteger|int {
        $num = is_int($numerator) ? BigInteger::fromInt($numerator) : $numerator;
        $den = is_int($denominator) ? BigInteger::fromInt($denominator) : $denominator;

        if ($den->isZero()) {
            throw new DivisionByZeroError('Rational denominator must be non-zero');
        }

        if ($num->isZero()) {
            return 0;
        }

        // Force denominator positive.
        if ($den->signum() < 0) {
            $num = $num->negate();
            $den = $den->negate();
        }

        $gcd = $num->abs()->gcd($den);
        if (!$gcd->isOne()) {
            $num = $num->divide($gcd);
            $den = $den->divide($gcd);
        }

        if ($den->isOne()) {
            return $num->fitsInPhpInt() ? $num->toInt() : $num;
        }

        return new self($num, $den);
    }

    public function numerator(): BigInteger
    {
        return $this->numerator;
    }

    public function denominator(): BigInteger
    {
        return $this->denominator;
    }

    public function add(self|BigInteger|int $other): self|BigInteger|int
    {
        [$num, $den] = $this->extractNumDen($other);
        // a/b + c/d = (a*d + c*b) / (b*d)
        $resultNum = $this->numerator->multiply($den)->add($num->multiply($this->denominator));
        $resultDen = $this->denominator->multiply($den);
        return self::create($resultNum, $resultDen);
    }

    public function subtract(self|BigInteger|int $other): self|BigInteger|int
    {
        [$num, $den] = $this->extractNumDen($other);
        $resultNum = $this->numerator->multiply($den)->subtract($num->multiply($this->denominator));
        $resultDen = $this->denominator->multiply($den);
        return self::create($resultNum, $resultDen);
    }

    public function multiply(self|BigInteger|int $other): self|BigInteger|int
    {
        [$num, $den] = $this->extractNumDen($other);
        return self::create(
            $this->numerator->multiply($num),
            $this->denominator->multiply($den),
        );
    }

    public function divide(self|BigInteger|int $other): self|BigInteger|int
    {
        [$num, $den] = $this->extractNumDen($other);
        if ($num->isZero()) {
            throw new DivisionByZeroError('Division by zero');
        }

        return self::create(
            $this->numerator->multiply($den),
            $this->denominator->multiply($num),
        );
    }

    public function negate(): self
    {
        return new self($this->numerator->negate(), $this->denominator);
    }

    public function abs(): self
    {
        if ($this->numerator->signum() >= 0) {
            return $this;
        }

        return new self($this->numerator->negate(), $this->denominator);
    }

    public function compareTo(self|BigInteger|int $other): int
    {
        [$num, $den] = $this->extractNumDen($other);
        // a/b vs c/d  →  sign(a*d - c*b) since b,d > 0
        $left = $this->numerator->multiply($den);
        $right = $num->multiply($this->denominator);
        return $left->compareTo($right);
    }

    public function equals(mixed $other): bool
    {
        if ($other instanceof self) {
            return $this->numerator->equals($other->numerator)
                && $this->denominator->equals($other->denominator);
        }

        if ($other instanceof BigInteger) {
            return $this->denominator->isOne() && $this->numerator->equals($other);
        }

        if (is_int($other)) {
            return $this->denominator->isOne() && $this->numerator->equals(BigInteger::fromInt($other));
        }

        return false;
    }

    public function hash(): int
    {
        return crc32(sprintf('%s/%s', $this->numerator, $this->denominator));
    }

    public function toFloat(): float
    {
        return (float) ((string) $this->numerator) / (float) ((string) $this->denominator);
    }

    /**
     * Truncates toward zero. Throws {@see OverflowException} if the integer
     * quotient does not fit in a native PHP int.
     */
    public function toInt(): int
    {
        return $this->numerator->divide($this->denominator)->toInt();
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
        return new self(
            $this->numerator,
            $this->denominator,
            $meta,
            $this->startLocation,
            $this->endLocation,
        );
    }

    public function setStartLocation(?SourceLocation $startLocation): static
    {
        return new self(
            $this->numerator,
            $this->denominator,
            $this->meta,
            $startLocation,
            $this->endLocation,
        );
    }

    public function setEndLocation(?SourceLocation $endLocation): static
    {
        return new self(
            $this->numerator,
            $this->denominator,
            $this->meta,
            $this->startLocation,
            $endLocation,
        );
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

    /**
     * @return array{0: BigInteger, 1: BigInteger}
     */
    private function extractNumDen(self|BigInteger|int $value): array
    {
        if ($value instanceof self) {
            return [$value->numerator, $value->denominator];
        }

        if ($value instanceof BigInteger) {
            return [$value, BigInteger::one()];
        }

        return [BigInteger::fromInt($value), BigInteger::one()];
    }
}
