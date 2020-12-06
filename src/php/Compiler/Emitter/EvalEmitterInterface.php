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
     * @throws RuntimeException|Throwable
     *
     * @return mixed
     */
    public function eval(string $code);
}
