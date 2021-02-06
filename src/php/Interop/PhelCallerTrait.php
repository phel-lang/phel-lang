<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Runtime\RuntimeFactory;

trait PhelCallerTrait
{
    /**
     * @param mixed[] $arguments
     *
     * @return mixed
     */
    private static function callPhel(string $namespace, string $definitionName, ...$arguments)
    {
        $fn = self::getPhelDefinition($namespace, $definitionName);

        return $fn(...$arguments);
    }

    /**
     * @return mixed
     */
    private static function getPhelDefinition(string $namespace, string $definitionName)
    {
        $rt = RuntimeFactory::getInstance();
        $rt->loadNs($namespace);

        return $GLOBALS['__phel'][$namespace][$definitionName];
    }
}
