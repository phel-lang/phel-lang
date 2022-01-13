<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

final class Registry
{
    /** @var array<string, array<string, mixed>> */
    private $definitions;

    /** @var array<string, array<string, mixed>> */
    private $definitionsMetaData;

    /** @var ?self */
    private static $instance = null;

    public function __construct()
    {
        $this->clear();
    }

    /**
     * @return self
     */
    public static function getInstance()
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

    public function getDefinition(string $ns, string $name): mixed
    {
        return $this->definitions[$ns][$name] ?? null;
    }

    public function getDefintionMetaData(string $ns, string $name): ?PersistentMapInterface
    {
        return $this->definitionsMetaData[$ns][$name] ?? null;
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
