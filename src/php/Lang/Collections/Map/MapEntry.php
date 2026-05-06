<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Countable;
use IteratorAggregate;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\EqualsInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\SourceLocationInterface;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;
use Stringable;
use Traversable;

use function is_bool;
use function is_string;
use function sprintf;

/**
 * Typed two-element entry returned by map iteration helpers.
 *
 * Equal to a two-element {@see PersistentVectorInterface} by value, so
 * callers that destructure `[k v]` keep working; the difference is that
 * `(map-entry? x)` distinguishes a real entry from a coincidental
 * 2-vector.
 */
final readonly class MapEntry implements TypeInterface, Stringable, Countable, IteratorAggregate
{
    private function __construct(
        private mixed $key,
        private mixed $value,
        private ?PersistentMapInterface $meta = null,
        private ?SourceLocation $startLocation = null,
        private ?SourceLocation $endLocation = null,
    ) {}

    public function __toString(): string
    {
        return sprintf('[%s %s]', $this->render($this->key), $this->render($this->value));
    }

    public static function create(mixed $key, mixed $value): self
    {
        return new self($key, $value);
    }

    public function key(): mixed
    {
        return $this->key;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function count(): int
    {
        return 2;
    }

    public function getIterator(): Traversable
    {
        yield $this->key;
        yield $this->value;
    }

    public function equals(mixed $other): bool
    {
        if ($other instanceof self) {
            return $this->scalarEquals($this->key, $other->key)
                && $this->scalarEquals($this->value, $other->value);
        }

        if ($other instanceof PersistentVectorInterface && $other->count() === 2) {
            return $this->scalarEquals($this->key, $other->get(0))
                && $this->scalarEquals($this->value, $other->get(1));
        }

        return false;
    }

    public function hash(): int
    {
        return TypeFactory::getInstance()
            ->persistentVectorFromArray([$this->key, $this->value])
            ->hash();
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->key, $this->value, $meta, $this->startLocation, $this->endLocation);
    }

    public function setStartLocation(?SourceLocation $startLocation): static
    {
        return new self($this->key, $this->value, $this->meta, $startLocation, $this->endLocation);
    }

    public function setEndLocation(?SourceLocation $endLocation): static
    {
        return new self($this->key, $this->value, $this->meta, $this->startLocation, $endLocation);
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

    private function scalarEquals(mixed $a, mixed $b): bool
    {
        if ($a instanceof EqualsInterface) {
            return $a->equals($b);
        }

        if ($b instanceof EqualsInterface) {
            return $b->equals($a);
        }

        return $a === $b;
    }

    private function render(mixed $value): string
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        }

        if ($value === null) {
            return 'nil';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
