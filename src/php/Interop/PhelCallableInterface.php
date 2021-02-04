<?php

declare(strict_types=1);

namespace Phel\Interop;

interface PhelCallableInterface
{
    /**
     * @param mixed[] $arguments
     *
     * @return mixed
     */
    public function callPhel(string $namespace, string $definitionName, ...$arguments);
}
