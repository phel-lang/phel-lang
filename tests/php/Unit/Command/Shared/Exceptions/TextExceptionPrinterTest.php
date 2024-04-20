<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Shared\Exceptions;

use Phel\Command\Domain\Shared\ErrorLog\ErrorLogInterface;
use Phel\Command\Domain\Shared\Exceptions\ExceptionArgsPrinterInterface;
use Phel\Command\Domain\Shared\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Domain\Shared\Exceptions\TextExceptionPrinter;
use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Transpiler\Domain\Parser\ReadModel\CodeSnippet;
use PHPUnit\Framework\TestCase;

final class TextExceptionPrinterTest extends TestCase
{
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

        $exceptionPrinter->printException($exception, $codeSnippet);
    }

    private function stubColorStyle(): ColorStyleInterface
    {
        $colorStyle = $this->createStub(ColorStyleInterface::class);
        $colorStyle->method('blue')->willReturnCallback(static fn (string $msg): string => $msg);
        $colorStyle->method('red')->willReturnCallback(static fn (string $msg): string => $msg);

        return $colorStyle;
    }
}
