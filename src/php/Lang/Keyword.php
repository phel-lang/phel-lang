<?php

declare(strict_types=1);

namespace Phel\Lang;

use Override;
use Phel\Lang\Collections\Map\PersistentMapInterface;

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

    public function __invoke(
        PersistentMapInterface $obj,
        float|bool|int|string|TypeInterface|null $default = null,
    ) {
        return $obj[$this] ?? $default;
    }

    public static function create(string $name, ?string $namespace = null): self
    {
        $key = $namespace !== null && $namespace !== ''
            ? $namespace . '/' . $name
            : $name;

        return self::$internPool[$key] ??= new self($namespace, $name);
    }

    /**
     * @deprecated in favor of create()
     */
    public static function createForNamespace(string $namespace, string $name): self
    {
        return self::create($name, $namespace);
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
