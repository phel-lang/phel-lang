<?php

declare(strict_types=1);

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\EmptyList;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\Variable;

/**
 * Static proxy to TypeFactory.
 *
 * @mixin TypeFactory
 *
 * Factory helpers:
 * @method static PersistentMapInterface     emptyPersistentMap()
 * @method static PersistentMapInterface     persistentMapFromKVs(mixed ...$kvs)
 * @method static PersistentMapInterface     persistentMapFromArray(array<string|int, mixed> $kvs)
 * @method static PersistentHashSetInterface persistentHashSetFromArray(list<mixed> $values)
 * @method static EmptyList                  emptyPersistentList()
 * @method static PersistentListInterface    persistentListFromArray(list<mixed> $values)
 * @method static PersistentVectorInterface  emptyPersistentVector()
 * @method static PersistentVectorInterface  persistentVectorFromArray(list<mixed> $values)
 *
 * Language values:
 * @method static Variable                   variable(mixed $value)
 * @method static Symbol                     symbol(string $name)
 * @method static Keyword                    keyword(string $name, ?string $namespace = null)
 *
 * Utilities:
 * @method static EqualizerInterface         getEqualizer()
 * @method static HasherInterface            getHasher()
 */
final class PhelType
{
    /**
     * Proxy undefined static method calls to the {@see TypeFactory} singleton.
     *
     * @param  list<mixed>  $arguments
     * @return mixed
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
