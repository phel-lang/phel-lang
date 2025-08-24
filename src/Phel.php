<?php

declare(strict_types=1);

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Registry;
use Phel\Lang\TypeFactory;
use Phel\Phel as InternalPhel;

/**
 * Public API for Phel.
 *
 * @mixin Registry
 * @mixin TypeFactory
 */
final class Phel extends InternalPhel
{
    /**
     * Proxy undefined static method calls to
     * - {@see Registry} singleton.
     * - {@see TypeFactory} singleton.
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

        $typeFactory = TypeFactory::getInstance();
        if (is_callable([$typeFactory, $name])) {
            return $typeFactory->$name(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method "%s" does not exist', $name));
    }

    /**
     * Create a persistent vector from an array of values.
     *
     * @param list<mixed> $values
     */
    public static function vector(array $values = []): PersistentVectorInterface
    {
        return TypeFactory::getInstance()->persistentVectorFromArray($values);
    }

    /**
     * Create a persistent list from an array of values.
     *
     * @param list<mixed> $values
     */
    public static function list(array $values = []): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray($values);
    }

    /**
     * Create a persistent map from key-value pairs.
     *
     * @param list<mixed> $kvs
     */
    public static function map(...$kvs): PersistentMapInterface
    {
        $typeFactory = TypeFactory::getInstance();
        if (count($kvs) === 1 && is_array($kvs[0])) {
            return $typeFactory->persistentMapFromArray($kvs[0]);
        }

        return $typeFactory->persistentMapFromKVs(...$kvs);
    }

    /**
     * Create a persistent hash set from an array of values.
     *
     * @param list<mixed> $values
     */
    public static function set(array $values = []): PersistentHashSetInterface
    {
        return TypeFactory::getInstance()->persistentHashSetFromArray($values);
    }

    /**
     * Get a reference to a stored definition.
     *
     * @noinspection PhpUnused
     * @see GlobalVarEmitter
     *
     * @return mixed Reference to the stored definition.
     */
    public static function &getDefinitionReference(string $ns, string $name): mixed
    {
        return Registry::getInstance()->getDefinitionReference($ns, $name);
    }
}
