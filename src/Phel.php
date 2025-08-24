<?php

declare(strict_types=1);

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
