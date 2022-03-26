<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Printer\Printer;

final class Keyword extends AbstractType implements IdenticalInterface, FnInterface, NamedInterface
{
    use MetaTrait;

    private ?string $namespace;
    private string $name;
    private int $hash;

    /** @var array<string, Keyword> */
    private static $refStore = [];

    private function __construct(?string $namespace, string $name)
    {
        $this->name = $name;
        $this->namespace = $namespace;
        if ($namespace) {
            $this->hash = crc32(':' . $namespace . '/' . $name);
        } else {
            $this->hash = crc32(':' . $name);
        }
    }

    /**
     * @param PersistentMapInterface $obj
     * @param TypeInterface|string|float|int|bool|null $default
     */
    public function __invoke($obj, $default = null)
    {
        return $obj[$this] ?? $default;
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
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
        if ($this->namespace) {
            return $this->namespace . '/' . $this->name;
        }

        return $this->name;
    }

    public function hash(): int
    {
        return $this->hash;
    }

    public function equals($other): bool
    {
        return $this->identical($other);
    }

    public function identical($other): bool
    {
        return $other instanceof self
            && $this->name == $other->getName()
            && $this->namespace == $other->getNamespace();
    }
}
