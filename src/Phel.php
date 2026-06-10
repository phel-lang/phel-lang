<?php

declare(strict_types=1);

use Phel\Lang\Atom;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Queue\PersistentQueue;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\DynamicScope;
use Phel\Lang\Keyword;
use Phel\Lang\PhelVarStateRegistry;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Phel as InternalPhel;

/**
 * Public API for Phel.
 *
 * @mixin Registry
 */
final class Phel extends InternalPhel
{
    /**
     * Proxy undefined static method calls to
     * - {@see Registry} singleton.
     *
     * @param list<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $registry = Registry::getInstance();
        if (\is_callable([$registry, $name])) {
            return $registry->$name(...$arguments);
        }

        throw new BadMethodCallException(\sprintf('Method "%s" does not exist', $name));
    }

    /**
     * Get a reference to a stored definition. This is part of the Registry but has to be redefined
     * because it is returning the reference to the definition.
     *
     * @noinspection PhpUnused
     *
     * @see GlobalVarEmitter
     *
     * @psalm-suppress UnsupportedReferenceUsage
     *
     * @return mixed reference to the stored definition
     */
    public static function &getDefinitionReference(string $ns, string $name): mixed
    {
        $definition = &Registry::getInstance()->getDefinitionReference($ns, $name);

        return $definition;
    }

    /**
     * Look up a global definition, consulting the fiber-local dynamic scope
     * first for vars tagged `:dynamic`. This is the read path emitted by
     * {@see Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\GlobalVarEmitter}
     * for non-reference reads.
     */
    public static function getDefinition(string $ns, string $name): mixed
    {
        // Hot path: every resolved symbol hits this. Probe the scope for
        // *any* live frame first (O(1)) so the common "no binding active"
        // case skips the string concat and stack walk entirely.
        $scope = DynamicScope::getInstance();
        if ($scope->hasAnyBinding() && $scope->hasBinding($ns, $name)) {
            return $scope->getBinding($ns, $name);
        }

        return Registry::getInstance()->getDefinition($ns, $name);
    }

    /**
     * Returns true if the given var carries `^:dynamic` metadata. Honors
     * per-var meta overrides installed via `alter-meta!` / `reset-meta!`.
     */
    public static function isDynamicVar(string $ns, string $name): bool
    {
        return PhelVarStateRegistry::getInstance()->isDynamic($ns, $name);
    }

    /**
     * Runtime target for the `set-var` special form. Routes by whether a
     * `binding` / `with-redefs` macro is currently recording on the
     * current fiber:
     *
     *  - Recording in `binding` mode + `:dynamic` var → stage into the
     *    pending fiber frame.
     *  - Recording in `binding` mode + non-dynamic var → throw, pointing
     *    callers at `with-redefs` for tests.
     *  - Recording in `redefs` mode → record old value for restore, then
     *    mutate the registry. Allowed for any var.
     *  - Not recording → plain registry mutation, as `set-var` always did.
     */
    public static function setVar(string $ns, string $name, mixed $value): mixed
    {
        $registry = Registry::getInstance();
        $scope = DynamicScope::getInstance();

        if ($scope->isRecording()) {
            $mode = $scope->currentRecordingMode();

            if ($mode === DynamicScope::MODE_BINDING) {
                if (!self::isDynamicVar($ns, $name)) {
                    throw new InvalidArgumentException(\sprintf(
                        "Can't dynamically bind non-dynamic var: %s/%s. Tag the def with ^:dynamic, or use `with-redefs` to rebind for tests.",
                        $ns,
                        $name,
                    ));
                }

                $scope->recordDynamic($ns, $name, $value);
                return $value;
            }

            // MODE_REDEFS: capture previous value and fall through to mutate.
            $scope->recordRedef($ns, $name, $registry->getDefinition($ns, $name));
        }

        $registry->addDefinition($ns, $name, $value, $registry->getDefinitionMetaData($ns, $name));
        return $value;
    }

    /**
     * Mutate the topmost active dynamic frame slot for the named var.
     * Backing call for `var-set`. Throws when the var is not currently
     * bound on the calling fiber's stack.
     */
    public static function varSet(string $ns, string $name, mixed $value): mixed
    {
        $scope = DynamicScope::getInstance();
        if (!$scope->setBinding($ns, $name, $value)) {
            throw new InvalidArgumentException(\sprintf(
                "Can't change/establish root binding of %s/%s with var-set; no thread-local binding is active.",
                $ns,
                $name,
            ));
        }

        return $value;
    }

    /**
     * Open a pending fiber-local binding recording. The subsequent
     * `set-var` calls emitted by the `binding` macro feed into it, and
     * {@see self::commitAndRunBindingFrame()} consumes it. Use
     * {@see self::openRedefsFrame()} for `with-redefs`-style mutation.
     */
    public static function openBindingFrame(): void
    {
        DynamicScope::getInstance()->startRecording(DynamicScope::MODE_BINDING);
    }

    /**
     * Open a pending fiber-local recording in `with-redefs` mode: any
     * var (dynamic or not) may be re-bound, with the previous root
     * captured for restore on exit.
     */
    public static function openRedefsFrame(): void
    {
        DynamicScope::getInstance()->startRecording(DynamicScope::MODE_REDEFS);
    }

    /**
     * Clean up a binding recording that was never committed because a
     * value expression threw between `openBindingFrame` and
     * {@see self::commitAndRunBindingFrame()}. Safe to call as the
     * `finally` branch of a `binding` expansion even on the success
     * path: it no-ops when no pending recording remains on this fiber.
     */
    public static function abortBindingFrameIfOpen(): void
    {
        $scope = DynamicScope::getInstance();
        if (!$scope->isRecording()) {
            return;
        }

        $entry = $scope->popRecording();
        $registry = Registry::getInstance();
        foreach (array_reverse($entry['redefs']) as [$ns, $name, $prev]) {
            $registry->addDefinition($ns, $name, $prev, $registry->getDefinitionMetaData($ns, $name));
        }
    }

    /**
     * Close the pending binding recording, push its dynamic values as
     * a fiber-local frame, run `$body`, then always pop the frame and
     * undo any with-redefs mutations (even on exception).
     */
    public static function commitAndRunBindingFrame(Closure $body): mixed
    {
        $scope = DynamicScope::getInstance();
        $entry = $scope->popRecording();
        $dynamic = $entry['dynamic'];
        $redefs = $entry['redefs'];

        try {
            return $dynamic === []
                ? $body()
                : $scope->withFrame($dynamic, $body);
        } finally {
            $registry = Registry::getInstance();
            foreach (array_reverse($redefs) as [$ns, $name, $prev]) {
                $registry->addDefinition($ns, $name, $prev, $registry->getDefinitionMetaData($ns, $name));
            }
        }
    }

    /**
     * Snapshot the current fiber's dynamic bindings for conveyance into
     * a new fiber (`future`/`async`/`future-fiber`).
     *
     * @return array<string, mixed>
     */
    public static function snapshotDynamicBindings(): array
    {
        return DynamicScope::getInstance()->snapshot();
    }

    /**
     * Install a snapshotted frame while executing `$body`. Used by the
     * fiber entry point of conveyed futures.
     *
     * @param array<string, mixed> $frame
     */
    public static function withDynamicBindings(array $frame, Closure $body): mixed
    {
        return DynamicScope::getInstance()->withFrame($frame, $body);
    }

    /**
     * Create a persistent vector from an array of values.
     *
     * @param list<mixed>|null $values
     *
     * @return PersistentVectorInterface<mixed>
     */
    public static function vector(?array $values = []): PersistentVectorInterface
    {
        return TypeFactory::getInstance()->persistentVectorFromArray($values ?? []);
    }

    /**
     * Create a persistent list from an array of values.
     *
     * @param array<int, mixed>|null $values
     *
     * @return PersistentListInterface<mixed>
     */
    public static function list(?array $values = []): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray($values ?? []);
    }

    /**
     * Create a non-list persistent list, used as the seq view over
     * non-list collections (vector, set, sorted map/set, php array).
     * The resulting value satisfies `seq?` but not `list?`.
     *
     * @param list<mixed>|null $values
     *
     * @return PersistentListInterface<mixed>
     */
    public static function seqList(?array $values = []): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentSeqListFromArray($values ?? []);
    }

    /**
     * Create a persistent map from key-value pairs.
     *
     * @param mixed ...$kvs
     *
     * @return PersistentMapInterface<mixed, mixed>
     */
    public static function map(...$kvs): PersistentMapInterface
    {
        $typeFactory = TypeFactory::getInstance();
        if (\count($kvs) === 1) {
            $firstArgument = $kvs[0] ?? null;

            if (\is_array($firstArgument)) {
                return $typeFactory->persistentMapFromArray($firstArgument);
            }

            if ($firstArgument === null) {
                return $typeFactory->persistentMapFromArray([]);
            }
        }

        return $typeFactory->persistentMapFromKVs(...$kvs);
    }

    /**
     * Source-location three-tuple used by `defn` / `def` to tag generated
     * metadata. Centralised here so the compiler emits a single helper
     * call per location, rather than three keyword interns plus a map
     * allocation, on every `addDefinition` site. The keyword keys live in
     * `static` slots so they intern once per process and are reused by
     * every location map.
     *
     * @return PersistentMapInterface<mixed, mixed>
     */
    public static function location(string $file, int $line, int $column): PersistentMapInterface
    {
        static $kwFile, $kwLine, $kwColumn;
        $kwFile ??= self::keyword('file');
        $kwLine ??= self::keyword('line');
        $kwColumn ??= self::keyword('column');

        return self::map($kwFile, $file, $kwLine, $line, $kwColumn, $column);
    }

    /**
     * Create a persistent hash set from an array of values.
     *
     * @param list<mixed>|null $values
     *
     * @return PersistentHashSetInterface<mixed>
     */
    public static function set(?array $values = []): PersistentHashSetInterface
    {
        return TypeFactory::getInstance()->persistentHashSetFromArray($values ?? []);
    }

    /**
     * Create a persistent FIFO queue from an array of values.
     *
     * @param list<mixed>|null $values
     */
    public static function queue(?array $values = []): PersistentQueue
    {
        return TypeFactory::getInstance()->persistentQueueFromArray($values ?? []);
    }

    /**
     * Create a persistent sorted map from key-value pairs.
     *
     * @return PersistentMapInterface<mixed, mixed>
     */
    public static function sortedMap(mixed ...$kvs): PersistentMapInterface
    {
        $typeFactory = TypeFactory::getInstance();
        if (\count($kvs) === 1 && \is_array($kvs[0])) {
            return $typeFactory->persistentSortedMapFromArray($kvs[0]);
        }

        return $typeFactory->persistentSortedMapFromArray($kvs);
    }

    /**
     * Create a persistent sorted map with a custom comparator.
     *
     * @return PersistentMapInterface<mixed, mixed>
     */
    public static function sortedMapBy(callable $comparator, mixed ...$kvs): PersistentMapInterface
    {
        $typeFactory = TypeFactory::getInstance();
        if (\count($kvs) === 1 && \is_array($kvs[0])) {
            return $typeFactory->persistentSortedMapFromArray($kvs[0], $comparator);
        }

        return $typeFactory->persistentSortedMapFromArray($kvs, $comparator);
    }

    /**
     * Create a persistent sorted set from an array of values.
     *
     * @param list<mixed>|null $values
     *
     * @return PersistentHashSetInterface<mixed>
     */
    public static function sortedSet(?array $values = []): PersistentHashSetInterface
    {
        return TypeFactory::getInstance()->persistentSortedSetFromArray($values ?? []);
    }

    /**
     * Create a persistent sorted set with a custom comparator.
     *
     * @param list<mixed>|null $values
     *
     * @return PersistentHashSetInterface<mixed>
     */
    public static function sortedSetBy(callable $comparator, ?array $values = []): PersistentHashSetInterface
    {
        return TypeFactory::getInstance()->persistentSortedSetFromArray($values ?? [], $comparator);
    }

    /**
     * @template T
     *
     * @param T                                         $value The initial value of the atom
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return Atom<T>
     */
    public static function atom($value, ?PersistentMapInterface $meta = null): Atom
    {
        return new Atom($meta, $value);
    }

    public static function symbol(string $name): Symbol
    {
        return Symbol::create($name);
    }

    public static function keyword(string $name, ?string $namespace = null): Keyword
    {
        return Keyword::create($name, $namespace);
    }
}
