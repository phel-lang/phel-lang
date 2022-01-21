<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

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
        if (self::$instance === null) {
            self::$instance = new Registry();
        }

        return self::$instance;
    }

    public function clear(): void
    {
        $this->definitions = [];
        $this->definitionsMetaData = [];
    }

    /**
     * @param mixed $value
     */
    public function addDefinition(string $ns, string $name, $value, ?PersistentMapInterface $metaData = null): void
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

    public function getDefinitionMetaData(string $ns, string $name): ?PersistentMapInterface
    {
        if (array_key_exists($ns, $this->definitions) && array_key_exists($name, $this->definitions[$ns])) {
            return $this->definitionsMetaData[$ns][$name] ?? TypeFactory::getInstance()->emptyPersistentMap();
        }

        return null;
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
