<?php

declare(strict_types=1);

namespace Phel\Compiler\Evaluator;

use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Throwable;

final class EvalEvaluator implements EvaluatorInterface
{
    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return mixed
     */
    public function eval(string $code)
    {
        try {
            return eval($code);
        } catch (Throwable $e) {
            throw CompiledCodeIsMalformedException::fromThrowable($e);
        }
    }
}
