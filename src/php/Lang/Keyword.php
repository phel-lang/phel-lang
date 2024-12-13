<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

final class Keyword extends AbstractType implements IdenticalInterface, FnInterface, NamedInterface
{
    use MetaTrait;

    private readonly int $hash;

    /** @var array<string, Keyword> */
    private static array $refStore = [];

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

    public static function create(string $name): self
    {
        return new self(null, $name);
    }

    public static function createForNamespace(string $namespace, string $name): self
    {
        return new self($namespace, $name);
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
        return ($other instanceof self)
            && $this->name === $other->getName()
            && $this->namespace === $other->getNamespace();
    }
}
