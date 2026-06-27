<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Run\Application\EvalExecutor;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Printer\PrinterInterface;
use PHPUnit\Framework\TestCase;

final class EvalExecutorTest extends TestCase
{
    private ?CompileOptions $capturedOptions = null;

    public function test_eval_defaults_to_optimization_level_zero(): void
    {
        $executor = $this->createExecutor();

        self::assertTrue($executor->execute('(+ 1 2)'));
        self::assertNotNull($this->capturedOptions);
        self::assertSame(0, $this->capturedOptions->getOptimizationLevel());
    }

    public function test_eval_uses_configured_optimization_level(): void
    {
        $executor = $this->createExecutor(optimizationLevel: 2);

        self::assertTrue($executor->execute('(+ 1 2)'));
        self::assertNotNull($this->capturedOptions);
        self::assertSame(2, $this->capturedOptions->getOptimizationLevel());
    }

    private function createExecutor(int $optimizationLevel = 0): EvalExecutor
    {
        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $compilerFacade->method('hasBalancedParentheses')->willReturn(true);
        $compilerFacade->method('eval')
            ->willReturnCallback(function (string $code, CompileOptions $options): mixed {
                $this->capturedOptions = $options;

                return 3;
            });

        $printer = $this->createStub(PrinterInterface::class);
        $printer->method('print')->willReturn('3');

        return new EvalExecutor(
            $this->createStub(ReplCommandIoInterface::class),
            $this->createStub(ColorStyleInterface::class),
            $printer,
            $compilerFacade,
            $optimizationLevel,
        );
    }
}
