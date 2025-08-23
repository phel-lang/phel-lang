<?php

declare(strict_types=1);

namespace Phel\Lang;

use BadMethodCallException;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\EmptyList;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function is_callable;
use function sprintf;

/**
 * @method static PersistentMapInterface emptyPersistentMap()
 * @method static PersistentMapInterface persistentMapFromKVs(mixed ...$kvs)
 * @method static PersistentMapInterface persistentMapFromArray(array $kvs)
 * @method static PersistentHashSetInterface persistentHashSetFromArray(array $values)
 * @method static EmptyList emptyPersistentList()
 * @method static PersistentListInterface persistentListFromArray(array $values)
 * @method static PersistentVectorInterface emptyPersistentVector()
 * @method static PersistentVectorInterface persistentVectorFromArray(array $values)
 * @method static Variable variable(mixed $value)
 * @method static Symbol symbol(string $name)
 * @method static Keyword keyword(string $name)
 * @method static EqualizerInterface getEqualizer()
 * @method static HasherInterface getHasher()
 */
final class Type
{
    /**
     * Proxy static method calls the TypeFactory instance.
     *
     * @param list<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $factory = TypeFactory::getInstance();
        if (is_callable([$factory, $name])) {
            return $factory->$name(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method "%s" does not exist', $name));
    }
}
