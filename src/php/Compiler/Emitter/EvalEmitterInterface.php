<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Exceptions\CompiledCodeIsMalformedException;
use Phel\Exceptions\FileException;

interface EvalEmitterInterface
{
    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return mixed
     */
    public function eval(string $code);
}
