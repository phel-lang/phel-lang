<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Override;

final class Keyword extends AbstractType implements IdenticalInterface, FnInterface, NamedInterface
{
    use MetaTrait;

    /** @var array<string, self> */
    private static array $internPool = [];

    private readonly int $hash;

    private function __construct(
        private readonly ?string $namespace,
        private readonly string $name,
    ) {
        $this->hash = $namespace !== null && $namespace !== ''
            ? crc32(':' . $namespace . '/' . $name)
            : crc32(':' . $name);
    }

    /**
     * Callable behaviour for keyword-as-accessor. Accepts any `ArrayAccess`
     * container so both persistent and transient maps work (previously the
     * signature was narrowed to `PersistentMapInterface`, which raised a
     * `TypeError` when called against a transient map).
     */
    public function __invoke(
        ArrayAccess $obj,
        float|bool|int|string|TypeInterface|null $default = null,
    ) {
        return $obj[$this] ?? $default;
    }

    #[Override]
    public function __toString(): string
    {
        if ($this->namespace !== null && $this->namespace !== '') {
            return ':' . $this->namespace . '/' . $this->name;
        }

        return ':' . $this->name;
    }

    public static function create(string $name, ?string $namespace = null): self
    {
        $key = $namespace !== null && $namespace !== ''
            ? $namespace . '/' . $name
            : $name;

        return self::$internPool[$key] ??= new self($namespace, $name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getFullName(): string
    {
        if ($this->namespace !== null && $this->namespace !== '') {
            return $this->namespace . '/' . $this->name;
        }

        return $this->name;
    }

    public function hash(): int
    {
        return $this->hash;
    }

    public function equals(mixed $other): bool
    {
        return $this->identical($other);
    }

    public function identical(mixed $other): bool
    {
        return $this === $other;
    }

    /**
     * Interned keywords are shared instances — source location is
     * per-occurrence, not per-keyword, so mutation must be suppressed.
     */
    #[Override]
    public function setStartLocation(?SourceLocation $startLocation): static
    {
        return $this;
    }

    #[Override]
    public function setEndLocation(?SourceLocation $endLocation): static
    {
        return $this;
    }
}
