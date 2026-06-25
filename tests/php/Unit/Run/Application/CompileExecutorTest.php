<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Run\Application\CompileExecutor;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;

final class CompileExecutorTest extends TestCase
{
    private ?CompileOptions $capturedOptions = null;

    public function test_compile_defaults_to_optimization_level_zero(): void
    {
        $executor = new CompileExecutor($this->createCapturingCompilerFacade());

        $executor->execute('(+ 1 2)', static function (string $out): void {}, static function (string $err): void {});

        self::assertNotNull($this->capturedOptions);
        self::assertSame(0, $this->capturedOptions->getOptimizationLevel());
        self::assertTrue($this->capturedOptions->isEmitOnly());
    }

    public function test_compile_uses_configured_optimization_level(): void
    {
        $executor = new CompileExecutor($this->createCapturingCompilerFacade(), optimizationLevel: 2);

        $executor->execute('(+ 1 2)', static function (string $out): void {}, static function (string $err): void {});

        self::assertNotNull($this->capturedOptions);
        self::assertSame(2, $this->capturedOptions->getOptimizationLevel());
    }

    public function test_empty_output_reports_the_discarded_value_on_stderr(): void
    {
        $executor = new CompileExecutor($this->createValueFoldingCompilerFacade());

        $out = '';
        $err = '';
        $ok = $executor->execute(
            '(+ 1 2)',
            static function (string $chunk) use (&$out): void {
                $out .= $chunk;
            },
            static function (string $chunk) use (&$err): void {
                $err .= $chunk;
            },
        );

        self::assertTrue($ok);
        self::assertSame('', $out, 'stdout stays clean when nothing compiles');
        self::assertStringContainsString('no PHP emitted', $err);
        self::assertStringContainsString('`3`', $err);
    }

    private function createValueFoldingCompilerFacade(): CompilerFacadeInterface
    {
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->method('hasBalancedParentheses')->willReturn(true);
        $compilerFacade->method('compile')
            ->willReturnCallback(static function (string $code, CompileOptions $options): EmitterResult {
                // Statement context drops the folded literal; expression context surfaces it.
                $php = $options->isEmitAsExpression() ? '3' : '';

                return new EmitterResult(false, $php, '', '');
            });

        return $compilerFacade;
    }

    private function createCapturingCompilerFacade(): CompilerFacadeInterface
    {
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->method('hasBalancedParentheses')->willReturn(true);
        $compilerFacade->method('compile')
            ->willReturnCallback(function (string $code, CompileOptions $options): EmitterResult {
                $this->capturedOptions = $options;

                return new EmitterResult(false, '3;', '', '');
            });

        return $compilerFacade;
    }
}
