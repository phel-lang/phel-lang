<?php

declare(strict_types=1);

namespace Phel\Build\Domain\ValueObject;

use InvalidArgumentException;

use function count;

/**
 * Value Object representing a Phel namespace.
 * Encapsulates namespace validation and common operations.
 */
final readonly class PhelNamespace
{
    private function __construct(
        private string $namespace,
    ) {
    }

    public static function fromString(string $namespace): self
    {
        if ($namespace === '') {
            throw new InvalidArgumentException('Namespace cannot be empty');
        }

        return new self($namespace);
    }

    public function toString(): string
    {
        return $this->namespace;
    }

    /**
     * Gets the parts of the namespace split by backslash.
     *
     * @return list<string>
     */
    public function getParts(): array
    {
        return explode('\\', $this->namespace);
    }

    /**
     * Gets the last part of the namespace (the "name" part).
     */
    public function getName(): string
    {
        $parts = $this->getParts();

        return end($parts) ?: $this->namespace;
    }

    /**
     * Gets the parent namespace (everything except the last part).
     */
    public function getParent(): ?self
    {
        $parts = $this->getParts();

        if (count($parts) <= 1) {
            return null;
        }

        array_pop($parts);

        return new self(implode('\\', $parts));
    }

    /**
     * Checks if this namespace starts with another namespace.
     */
    public function startsWith(self $prefix): bool
    {
        return str_starts_with($this->namespace, $prefix->namespace);
    }

    /**
     * Converts the namespace to a relative file path.
     */
    public function toRelativePath(string $extension = 'phel'): string
    {
        return str_replace('\\', DIRECTORY_SEPARATOR, $this->namespace) . '.' . $extension;
    }

    public function equals(self $other): bool
    {
        return $this->namespace === $other->namespace;
    }
}
