<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Runtime\RuntimeFactory;

final class PhelCaller implements PhelCallableInterface
{
    /**
     * @param mixed[] $arguments
     *
     * @return mixed
     */
    public function callPhel(string $namespace, string $definitionName, ...$arguments)
    {
        $fn = $this->getPhelDefinition($namespace, $definitionName);

        return $fn(...$arguments);
    }

    /**
     * @return mixed
     */
    private function getPhelDefinition(string $namespace, string $definitionName)
    {
        $rt = RuntimeFactory::getInstance();
        $rt->loadNs($namespace);

        return $GLOBALS['__phel'][$namespace][$definitionName];
    }
}
