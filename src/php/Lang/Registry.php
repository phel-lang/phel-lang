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
    /**
     * Set by `phel profile` to install a profiler hook. When non-null,
     * `addDefinition` wraps every `AbstractFn` value with a profiling proxy
     * before storing it. Off-state cost: one null-check per definition.
     */
    public static ?ProfilerHookInterface $profilerHook = null;

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
        PhelVarStateRegistry::getInstance()->clear();
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

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $metaData
     */
    public function addDefinition(string $ns, string $name, mixed $value, ?PersistentMapInterface $metaData = null): PhelVar
    {
        if (self::$profilerHook instanceof ProfilerHookInterface && $value instanceof AbstractFn) {
            $value = self::$profilerHook->wrapFn($value);
        }

        $this->definitions[$ns][$name] = $value;
        $this->definitionsMetaData[$ns][$name] = $metaData;
        PhelVarStateRegistry::getInstance()->invalidateDynamicCache($ns, $name);

        return new PhelVar($ns, $name);
    }

    /**
     * Returns a `PhelVar` handle to an existing definition. The slot must
     * already exist; callers should typically be the analyzer-emitted output
     * of the `(var sym)` special form, where resolution has already
     * established that the symbol points at a known def.
     */
    public function getVar(string $ns, string $name): PhelVar
    {
        if (!$this->isDefined($ns, $name)) {
            throw new RuntimeException(sprintf('Var "%s/%s" not found', $ns, $name));
        }

        return new PhelVar($ns, $name);
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

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
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
