<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding;

use Phel;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Destructure;
use Phel\Lang\Symbol;

/**
 * The read a map pattern puts in front of its lookups (#3479): a map is used
 * as is, anything else goes through {@see Destructure}.
 */
final class MapBindingForms
{
    /**
     * `(if (php/instanceof value PersistentMapInterface) value (Destructure/lookupSource value))`.
     *
     * @return PersistentListInterface<mixed>
     */
    public static function lookupSource(Symbol $value): PersistentListInterface
    {
        return self::readUnlessMap($value, 'lookupSource');
    }

    /**
     * The same read with `Destructure/kwargs`, what `:as` binds.
     *
     * @return PersistentListInterface<mixed>
     */
    public static function kwargs(Symbol $value): PersistentListInterface
    {
        return self::readUnlessMap($value, 'kwargs');
    }

    /**
     * @return PersistentListInterface<mixed>
     */
    private static function readUnlessMap(Symbol $value, string $method): PersistentListInterface
    {
        return Phel::list([
            Symbol::create(Symbol::NAME_IF),
            Phel::list([
                Symbol::create('php/instanceof'),
                $value,
                Symbol::create('\\' . PersistentMapInterface::class),
            ]),
            $value,
            Phel::list([
                Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL),
                Symbol::create('\\' . Destructure::class),
                Phel::list([Symbol::create($method), $value]),
            ]),
        ]);
    }
}
