<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\Runtime\Runtime;
use Phel\Runtime\RuntimeFactory;

trait CallPhelTrait
{
    /**
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
    public function getPhelDefinition(string $namespace, string $definitionName)
    {
        $rt = $this->getRuntime();
        $rt->loadNs($namespace);

        return $GLOBALS['__phel'][$namespace][$definitionName];
    }

    public function getRuntime(): Runtime
    {
        return RuntimeFactory::getInstance();
    }
}
