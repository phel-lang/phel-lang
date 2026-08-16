<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use RuntimeException;

use function array_key_exists;
use function sprintf;

/**
 * @phpstan-type RegistrySnapshot array{definitions: array<string, array<string, mixed>>, definitionsMetaData: array<string, array<string, mixed>>}
 */
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
     * @return RegistrySnapshot
     */
    public function snapshot(): array
    {
        return [
            'definitions' => $this->definitions,
            'definitionsMetaData' => $this->definitionsMetaData,
        ];
    }

    /**
     * @param RegistrySnapshot $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->definitions = $snapshot['definitions'];
        $this->definitionsMetaData = $snapshot['definitionsMetaData'];
    }

    /**
     * Stores a definition and returns a handle to it.
     *
     * Side effect: when {@see self::$profilerHook} is installed and `$value`
     * is an `AbstractFn`, the stored value is the profiling wrapper, not the
     * original function. Also clears the dynamic-flag cache for the slot.
     *
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

    /**
     * The root value of a definition, read in one static hop.
     *
     * This is what compiled code calls for a var that cannot be dynamically
     * bound, so it is one of the most-executed methods in a Phel program:
     * `\Phel::getDefinition()` reaches the same array after a facade call and
     * a dynamic-scope gate that only a `^:dynamic` var can ever trip, and
     * skipping both is 2.4x faster idle and 3.4x faster while any `binding`
     * frame is open (#3179).
     *
     * `with-redefs`, `phel.mock` and `dotrace` swap the root through
     * `addDefinition`, so a root read still observes every one of them.
     *
     * Do NOT rename: `GlobalVarEmitter` bakes this FQN into generated PHP, and
     * cached `.phel` artifacts keep calling it by that name.
     */
    public static function readRoot(string $ns, string $name): mixed
    {
        return self::getInstance()->definitions[$ns][$name] ?? null;
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

        /** @var PersistentMapInterface<mixed, mixed>|null $meta */
        $meta = $this->definitionsMetaData[$ns][$name] ?? TypeFactory::getInstance()->persistentMapFromArray([]);
        return $meta;
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
