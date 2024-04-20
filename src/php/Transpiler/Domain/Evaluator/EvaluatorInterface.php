<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Evaluator;

use Phel\Transpiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;

interface EvaluatorInterface
{
    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function eval(string $code): mixed;
}
