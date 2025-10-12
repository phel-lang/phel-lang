<?php

declare(strict_types=1);

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\Variable;
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
     * @param  string  $name
     * @param  list<mixed>  $arguments
     *
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $registry = Registry::getInstance();
        if (is_callable([$registry, $name])) {
            return $registry->$name(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method "%s" does not exist', $name));
    }

    /**
     * Get a reference to a stored definition. This is part of the Registry but has to be redefined
     * because it is returning the reference to the definition.
     *
     * @noinspection PhpUnused
     * @see GlobalVarEmitter
     *
     * @return mixed Reference to the stored definition.
     */
    /** @psalm-suppress UnsupportedReferenceUsage */
    public static function &getDefinitionReference(string $ns, string $name): mixed
    {
        $definition = &Registry::getInstance()->getDefinitionReference($ns, $name);

        return $definition;
    }

    /**
     * Create a persistent vector from an array of values.
     *
     * @param list<mixed>|null $values
     */
    public static function vector(?array $values = []): PersistentVectorInterface
    {
        return TypeFactory::getInstance()->persistentVectorFromArray($values ?? []);
    }

    /**
     * Create a persistent list from an array of values.
     *
     * @param list<mixed>|null $values
     */
    public static function list(?array $values = []): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray($values ?? []);
    }

    /**
     * Create a persistent map from key-value pairs.
     *
     * @param mixed ...$kvs
     */
    public static function map(...$kvs): PersistentMapInterface
    {
        $typeFactory = TypeFactory::getInstance();
        if (count($kvs) === 1) {
            $firstArgument = $kvs[0] ?? null;

            if (is_array($firstArgument)) {
                return $typeFactory->persistentMapFromArray($firstArgument);
            }

            if ($firstArgument === null) {
                return $typeFactory->persistentMapFromArray([]);
            }
        }

        return $typeFactory->persistentMapFromKVs(...$kvs);
    }

    /**
     * Create a persistent hash set from an array of values.
     *
     * @param list<mixed>|null $values
     */
    public static function set(?array $values = []): PersistentHashSetInterface
    {
        return TypeFactory::getInstance()->persistentHashSetFromArray($values ?? []);
    }

    /**
     * @template T
     * @param T $value The initial value of the variable
     * @return Variable<T>
     */
    public static function variable($value, ?PersistentMapInterface $meta = null): Variable
    {
        return new Variable($meta, $value);
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
