<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Printer\Printer;

final class Keyword extends AbstractType implements IdenticalInterface, FnInterface
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

    public static function create(string $name): Keyword
    {
        return new Keyword(null, $name);
    }

    public static function createForNamespace(string $namespace, string $name): Keyword
    {
        return new Keyword($namespace, $name);
    }

    /**
     * @param PersistentMapInterface $obj
     * @param TypeInterface|string|float|int|bool|null $default
     */
    public function __invoke($obj, $default = null)
    {
        return $obj[$this] ?? $default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
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

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }
}
