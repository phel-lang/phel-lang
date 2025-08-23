<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel;

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

    private function getPhelDefinition(string $namespace, string $definitionName): mixed
    {
        $phelNs = str_replace('-', '_', $namespace);

        return Phel::getDefinition($phelNs, $definitionName);
    }
}
