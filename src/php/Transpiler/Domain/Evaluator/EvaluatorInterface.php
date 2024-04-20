<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Evaluator;

use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\TrarnspiledCodeIsMalformedException;

interface EvaluatorInterface
{
    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @throws TrarnspiledCodeIsMalformedException
     * @throws FileException
     */
    public function eval(string $code): mixed;
}
