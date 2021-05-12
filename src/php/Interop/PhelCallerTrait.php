<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Runtime\RuntimeSingleton;

trait PhelCallerTrait
{
    /**
     * @param mixed[] $arguments
     *
     * @return mixed
     */
    private function callPhel(string $namespace, string $definitionName, ...$arguments)
    {
        $fn = $this->getPhelDefinition($namespace, $definitionName);

        return $fn(...$arguments);
    }

    /**
     * @return mixed
     */
    private function getPhelDefinition(string $namespace, string $definitionName)
    {
        $rt = RuntimeSingleton::getInstance();
        $rt->loadNs($namespace);
        $phelNs = str_replace('-', '_', $namespace);

        return $GLOBALS['__phel'][$phelNs][$definitionName];
    }
}
