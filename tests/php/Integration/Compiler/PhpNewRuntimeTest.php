<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Shared\CompileOptions;

final class PhpNewRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    public function test_dynamic_new_with_integer_literal_throws_descriptive_error(): void
    {
        $this->expectException(EvaluatedCodeException::class);
        $this->expectExceptionMessage('php/new expects a class name or object, int given (1)');

        $this->compilerFacade->eval(
            '(php/new 1)',
            new CompileOptions(),
        );
    }

    public function test_dynamic_new_with_bound_integer_throws_descriptive_error(): void
    {
        $this->expectException(EvaluatedCodeException::class);
        $this->expectExceptionMessage('php/new expects a class name or object, int given (42)');

        $this->compilerFacade->eval(
            '(let [x 42] (php/new x))',
            new CompileOptions(),
        );
    }
}
