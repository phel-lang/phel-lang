<?php

declare(strict_types=1);

namespace Phel\Interop;

final class PhelCaller
{
    use PhelCallerTrait;

    /**
     * @param mixed ...$arguments
     *
     * @return mixed
     */
    public function call(string $namespace, string $definitionName, ...$arguments)
    {
        return $this->callPhel($namespace, $definitionName, ...$arguments);
    }
}
