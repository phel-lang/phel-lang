<?php

declare(strict_types=1);

namespace Phel\Lang;

use InvalidArgumentException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Stringable;

use function class_exists;
use function crc32;
use function interface_exists;
use function is_object;
use function ltrim;
use function sprintf;

/**
 * First-class wrapper around a PHP class or interface FQN.
 *
 * PHP classes are exposed in Phel sources as bareword class names
 * resolved by the `(use ...)` directive and emitted as raw class FQN
 * strings. {@see PhpClass} promotes that string into a typed value so
 * `(class x)`, `(class? x)`, and class-keyed hierarchies (`derive`,
 * `ancestors`, `parents`) can distinguish a PHP class from an arbitrary
 * string at runtime.
 *
 * The wrapped FQN is normalised: any leading backslash is dropped, so
 * `Phel\Lang\Symbol` and `\Phel\Lang\Symbol` produce the equal value.
 */
final readonly class PhpClass implements TypeInterface, Stringable
{
    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function __construct(
        private string $fqn,
        private ?PersistentMapInterface $meta = null,
        private ?SourceLocation $startLocation = null,
        private ?SourceLocation $endLocation = null,
    ) {}

    public function __toString(): string
    {
        return $this->fqn;
    }

    /**
     * Builds a {@see PhpClass} from a class or interface FQN. Throws
     * {@see InvalidArgumentException} when the symbol is not a known
     * class or interface.
     */
    public static function fromName(string $fqn): self
    {
        $normalised = ltrim($fqn, '\\');
        if ($normalised === '') {
            throw new InvalidArgumentException('PhpClass name cannot be empty');
        }

        if (!class_exists($normalised) && !interface_exists($normalised)) {
            throw new InvalidArgumentException(
                sprintf("Unknown class or interface: '%s'", $fqn),
            );
        }

        return new self($normalised);
    }

    /**
     * Builds a {@see PhpClass} for the runtime class of `$value`.
     * Throws when `$value` is not an object.
     */
    public static function ofValue(mixed $value): self
    {
        if (!is_object($value)) {
            throw new InvalidArgumentException(
                sprintf("Expected an object, got '%s'", get_debug_type($value)),
            );
        }

        return new self($value::class);
    }

    public function name(): string
    {
        return $this->fqn;
    }

    public function isInstance(mixed $value): bool
    {
        return is_object($value) && is_a($value, $this->fqn);
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $other->fqn === $this->fqn;
    }

    public function hash(): int
    {
        return crc32($this->fqn);
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
        return new self($this->fqn, $meta, $this->startLocation, $this->endLocation);
    }

    public function setStartLocation(?SourceLocation $startLocation): static
    {
        return new self($this->fqn, $this->meta, $startLocation, $this->endLocation);
    }

    public function setEndLocation(?SourceLocation $endLocation): static
    {
        return new self($this->fqn, $this->meta, $this->startLocation, $endLocation);
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
