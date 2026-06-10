<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\FileCompiler;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

final class FileCompilerTest extends TestCase
{
    private ?CompileOptions $capturedOptions = null;

    public function test_compile_file_defaults_to_optimization_level_zero(): void
    {
        $compiler = new FileCompiler(
            $this->createCapturingCompilerFacade(),
            $this->createNamespaceExtractor(),
            $this->createStub(FileIoInterface::class),
        );

        $compiler->compileFile('/src/test.phel', '/out/test.php', false);

        self::assertNotNull($this->capturedOptions);
        self::assertSame(0, $this->capturedOptions->getOptimizationLevel());
    }

    public function test_compile_file_uses_constructor_optimization_level(): void
    {
        $compiler = new FileCompiler(
            $this->createCapturingCompilerFacade(),
            $this->createNamespaceExtractor(),
            $this->createStub(FileIoInterface::class),
            defaultOptimizationLevel: 2,
        );

        $compiler->compileFile('/src/test.phel', '/out/test.php', false);

        self::assertNotNull($this->capturedOptions);
        self::assertSame(2, $this->capturedOptions->getOptimizationLevel());
    }

    public function test_explicit_optimization_level_overrides_constructor_default(): void
    {
        $compiler = new FileCompiler(
            $this->createCapturingCompilerFacade(),
            $this->createNamespaceExtractor(),
            $this->createStub(FileIoInterface::class),
            defaultOptimizationLevel: 2,
        );

        $compiler->compileFile('/src/test.phel', '/out/test.php', false, 0);

        self::assertNotNull($this->capturedOptions);
        self::assertSame(0, $this->capturedOptions->getOptimizationLevel());
    }

    private function createCapturingCompilerFacade(): CompilerFacadeInterface
    {
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->method('compile')
            ->willReturnCallback(function (string $code, CompileOptions $options): EmitterResult {
                $this->capturedOptions = $options;

                return new EmitterResult(false, '$compiled = true;', '', '');
            });

        return $compilerFacade;
    }

    private function createNamespaceExtractor(): NamespaceExtractorInterface
    {
        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation('/src/test.phel', 'test\\ns', ['phel.core']),
        );

        return $namespaceExtractor;
    }
}
