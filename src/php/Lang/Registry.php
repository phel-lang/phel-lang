<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

use RuntimeException;

use function array_key_exists;

final class Registry
{
    /** @var array<string, array<string, mixed>> */
    private array $definitions = [];

    /** @var array<string, array<string, mixed>> */
    private array $definitionsMetaData = [];

    private static ?Registry $instance = null;

    private function __construct()
    {
        $this->clear();
    }

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function clear(): void
    {
        $this->definitions = [];
        $this->definitionsMetaData = [];
    }

    public function addDefinition(string $ns, string $name, mixed $value, ?PersistentMapInterface $metaData = null): void
    {
        $this->definitions[$ns][$name] = $value;
        $this->definitionsMetaData[$ns][$name] = $metaData;
    }

    public function hasDefinition(string $ns, string $name): bool
    {
        return isset($this->definitions[$ns][$name]);
    }

    public function getDefinition(string $ns, string $name): mixed
    {
        return $this->definitions[$ns][$name] ?? null;
    }

    public function &getDefinitionReference(string $ns, string $name): mixed
    {
        if (isset($this->definitions[$ns][$name])) {
            /** @psalm-suppress UnsupportedPropertyReferenceUsage */
            $value = &$this->definitions[$ns][$name];
            return $value;
        }

        throw new RuntimeException('Only variables can be returned by reference');
    }

    public function getDefinitionMetaData(string $ns, string $name): ?PersistentMapInterface
    {
        if (!array_key_exists($ns, $this->definitions)) {
            return null;
        }

        if (!array_key_exists($name, $this->definitions[$ns])) {
            return null;
        }

        return $this->definitionsMetaData[$ns][$name] ?? TypeFactory::getInstance()->emptyPersistentMap();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionInNamespace(string $ns): array
    {
        return $this->definitions[$ns] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getNamespaces(): array
    {
        return array_keys($this->definitions);
    }
}
