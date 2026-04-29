<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use RuntimeException;

use function array_key_exists;
use function sprintf;

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

    /**
     * @return array{definitions: array<string, array<string, mixed>>, definitionsMetaData: array<string, array<string, mixed>>}
     */
    public function snapshot(): array
    {
        return [
            'definitions' => $this->definitions,
            'definitionsMetaData' => $this->definitionsMetaData,
        ];
    }

    /**
     * @param array{definitions: array<string, array<string, mixed>>, definitionsMetaData: array<string, array<string, mixed>>} $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->definitions = $snapshot['definitions'];
        $this->definitionsMetaData = $snapshot['definitionsMetaData'];
    }

    public function addDefinition(string $ns, string $name, mixed $value, ?PersistentMapInterface $metaData = null): VarReference
    {
        $this->definitions[$ns][$name] = $value;
        $this->definitionsMetaData[$ns][$name] = $metaData;

        return new VarReference($ns, $name);
    }

    public function hasDefinition(string $ns, string $name): bool
    {
        return isset($this->definitions[$ns][$name]);
    }

    /**
     * Like {@see self::hasDefinition()} but treats a stored `null` as present.
     * Use this when you need to disambiguate "stored null" from "not defined".
     */
    public function isDefined(string $ns, string $name): bool
    {
        return isset($this->definitions[$ns])
            && array_key_exists($name, $this->definitions[$ns]);
    }

    public function hasNamespace(string $ns): bool
    {
        return isset($this->definitions[$ns]);
    }

    public function registerNamespace(string $ns): void
    {
        if (!isset($this->definitions[$ns])) {
            $this->definitions[$ns] = [];
            $this->definitionsMetaData[$ns] = [];
        }
    }

    public function removeNamespace(string $ns): void
    {
        unset($this->definitions[$ns], $this->definitionsMetaData[$ns]);
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

        throw new RuntimeException(sprintf('Definition "%s/%s" not found', $ns, $name));
    }

    public function getDefinitionMetaData(string $ns, string $name): ?PersistentMapInterface
    {
        if (!array_key_exists($ns, $this->definitions)
            || !array_key_exists($name, $this->definitions[$ns])
        ) {
            return null;
        }

        return $this->definitionsMetaData[$ns][$name] ?? Phel::map();
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
