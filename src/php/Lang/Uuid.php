<?php

declare(strict_types=1);

namespace Phel\Lang;

use InvalidArgumentException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Stringable;

use function chr;
use function crc32;
use function hexdec;
use function ord;
use function preg_match;
use function random_bytes;
use function sprintf;
use function strtolower;
use function substr;

/**
 * Canonical UUID value. Implements {@see TypeInterface} so reader
 * literals (`#uuid "..."`) and `phel.core/random-uuid` produce a
 * first-class typed value rather than a string.
 *
 * Constructed only via the static factories, which validate the
 * `8-4-4-4-12` hexadecimal shape and lowercase the input.
 */
final readonly class Uuid implements TypeInterface, Stringable
{
    private const string CANONICAL_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function __construct(
        private string $value,
        private ?PersistentMapInterface $meta = null,
        private ?SourceLocation $startLocation = null,
        private ?SourceLocation $endLocation = null,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Builds a `Uuid` from a canonical string. Throws when the string
     * does not match the `8-4-4-4-12` hexadecimal shape.
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::CANONICAL_REGEX, $value) !== 1) {
            throw new InvalidArgumentException(
                sprintf("Invalid UUID string: '%s'", $value),
            );
        }

        return new self(strtolower($value));
    }

    /**
     * Generates a random version 4 UUID.
     */
    public static function randomV4(): self
    {
        $bytes = random_bytes(16);
        // Set version 4 (bits 12-15 of time_hi_and_version).
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        // Set variant (bits 6-7 of clock_seq_hi_and_reserved).
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return new self(sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ));
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Returns the version digit (1-5) encoded in the UUID.
     */
    public function version(): int
    {
        return (int) hexdec($this->value[14]);
    }

    /**
     * Returns a keyword-like marker for the variant field: `ncs`,
     * `rfc-4122`, `microsoft`, or `reserved`.
     */
    public function variant(): string
    {
        $nibble = (int) hexdec($this->value[19]);

        return match (true) {
            ($nibble & 0x8) === 0 => 'ncs',
            ($nibble & 0x4) === 0 => 'rfc-4122',
            ($nibble & 0x2) === 0 => 'microsoft',
            default => 'reserved',
        };
    }

    public function isNil(): bool
    {
        return $this->value === '00000000-0000-0000-0000-000000000000';
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    public function hash(): int
    {
        return crc32($this->value);
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
        return new self($this->value, $meta, $this->startLocation, $this->endLocation);
    }

    public function setStartLocation(?SourceLocation $startLocation): static
    {
        return new self($this->value, $this->meta, $startLocation, $this->endLocation);
    }

    public function setEndLocation(?SourceLocation $endLocation): static
    {
        return new self($this->value, $this->meta, $this->startLocation, $endLocation);
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
}
