<?php

declare(strict_types=1);

use Phel\Lang\TypeFactory;

/**
 * Static proxy to TypeFactory.
 *
 * @mixin TypeFactory
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
