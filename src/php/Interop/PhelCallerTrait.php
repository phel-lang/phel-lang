<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Lang\Registry;

trait PhelCallerTrait
{
    /**
     * @param mixed[] $args
     *
     * @return mixed
     */
    private function callPhel(string $namespace, string $definitionName, ...$args)
    {
        $fn = $this->getPhelDefinition($namespace, $definitionName);

        return $fn(...$args);
    }

    /**
     * @return mixed
     */
    private function getPhelDefinition(string $namespace, string $definitionName)
    {
        $phelNs = str_replace('-', '_', $namespace);

        return Registry::getInstance()->getDefinition($phelNs, $definitionName);
    }
}
