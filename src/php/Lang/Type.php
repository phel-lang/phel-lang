<?php

declare(strict_types=1);

namespace Phel\Lang;

use BadMethodCallException;

use function is_callable;
use function sprintf;

final class Type
{
    /**
     * Proxy static method calls to the TypeFactory instance.
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
