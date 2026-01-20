<?php

declare(strict_types=1);

namespace Phel\Run\Application\Port;

use Phel\Compiler\Domain\ValueObject\CompileOptions;

/**
 * Driving port (primary port) for evaluating Phel code at runtime.
 */
interface EvaluatePhelCodeUseCase
{
    /**
     * Evaluates Phel code and returns the result.
     *
     * @return mixed The result of the executed code
     */
    public function execute(string $phelCode, CompileOptions $compileOptions): mixed;
}
