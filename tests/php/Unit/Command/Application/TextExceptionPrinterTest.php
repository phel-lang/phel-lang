<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Application;

use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;
use Phel\Shared\ColorStyleInterface;
use PHPUnit\Framework\TestCase;
use TypeError;

final class TextExceptionPrinterTest extends TestCase
{
    public function test_renders_phel_source_location_for_evaluated_code_exception(): void
    {
        $original = new class('boom') extends TypeError {
            public function __construct(string $message)
            {
                parent::__construct($message);
                $this->line = 3;
            }
        };
        $compiledCode = "// my-file.phel\n// ;;AAAA\nphp_body();";
        $exception = EvaluatedCodeException::fromThrowableAndCompiledCode($original, $compiledCode);

        $exceptionPrinter = new TextExceptionPrinter(
            $this->createStub(ExceptionArgsPrinterInterface::class),
            $this->stubColorStyle(),
            $this->createStub(MungeInterface::class),
            $this->createStub(FilePositionExtractorInterface::class),
            $this->createStub(ErrorLogInterface::class),
        );

        $output = $exceptionPrinter->getStackTraceString($exception);

        self::assertStringContainsString('TypeError', $output);
        self::assertStringContainsString('boom', $output);
        self::assertStringContainsString('in my-file.phel:1', $output);
    }

    public function test_print_exception(): void
    {
        $file = 'example-file.phel';

        $codeSnippet = new CodeSnippet(
            startLocation: new SourceLocation($file, line: 1, column: 1),
            endLocation: new SourceLocation($file, line: 1, column: 3),
            code: '(+ 1 2 3 unknown-symbol)',
        );

        $type = $this->createStub(AbstractType::class);
        $type->method('getStartLocation')->willReturn(new SourceLocation($file, line: 1, column: 9));
        $type->method('getEndLocation')->willReturn(new SourceLocation($file, line: 1, column: 23));

        $exception = AnalyzerException::withLocation('.', $type);

        $expectedOutput = <<<'MSG'
.
in example-file.phel:1

1| (+ 1 2 3 unknown-symbol)
            ^^^^^^^^^^^^^^

MSG;
        $errorLog = $this->createMock(ErrorLogInterface::class);
        $errorLog->expects(self::once())
            ->method('writeln')
            ->with($expectedOutput);

        $exceptionPrinter = new TextExceptionPrinter(
            $this->createStub(ExceptionArgsPrinterInterface::class),
            $this->stubColorStyle(),
            $this->createStub(MungeInterface::class),
            $this->createStub(FilePositionExtractorInterface::class),
            $errorLog,
        );

        $this->expectOutputString('');

        $exceptionPrinter->printException($exception, $codeSnippet);
    }

    private function stubColorStyle(): ColorStyleInterface
    {
        $colorStyle = $this->createStub(ColorStyleInterface::class);
        $colorStyle->method('blue')->willReturnCallback(static fn(string $msg): string => $msg);
        $colorStyle->method('red')->willReturnCallback(static fn(string $msg): string => $msg);

        return $colorStyle;
    }
}
