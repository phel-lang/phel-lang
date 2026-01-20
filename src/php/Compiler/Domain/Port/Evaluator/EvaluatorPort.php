<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Port\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;

/**
 * Driven port (secondary port) for code evaluation.
 * External dependency for executing compiled PHP code.
 */
interface EvaluatorPort
{
    /**
     * Evaluates the compiled PHP code and returns the result.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function eval(string $code): mixed;
}
