<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use RuntimeException;
use Throwable;

interface EvalEmitterInterface
{
    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @return mixed
     *
     * @throws RuntimeException|Throwable
     */
    public function eval(string $code);
}
