<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Run\Domain\Repl\EvalResult;
use Phel\Shared\Facade\CompilerFacadeInterface;

final readonly class StructuredEvaluator
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function eval(string $phelCode, CompileOptions $compileOptions): EvalResult
    {
        return EvalResult::fromEval($this->compilerFacade, $phelCode, $compileOptions);
    }
}
