<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Shared\Exceptions;

use Phel\Command\Shared\Exceptions\ExceptionArgsPrinterInterface;
use Phel\Command\Shared\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Shared\Exceptions\TextExceptionPrinter;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use PHPUnit\Framework\TestCase;

final class TextExceptionPrinterTest extends TestCase
{
    public function test_print_exception(): void
    {
        $file = 'example-file.phel';

        $codeSnippet = new CodeSnippet(
            $startLocation = new SourceLocation($file, $line = 1, $column = 1),
            $endLocation = new SourceLocation($file, $line = 1, $column = 3),
            '(+ 1 2 3 unknown-symbol)'
        );

        $type = $this->createStub(AbstractType::class);
        $type->method('getStartLocation')->willReturn(new SourceLocation($file, $line = 1, $column = 9));
        $type->method('getEndLocation')->willReturn(new SourceLocation($file, $line = 1, $column = 23));

        $exception = AnalyzerException::withLocation('Example code exception message', $type);

        $this->expectOutputString(
            <<<'MSG'
Example code exception message
in example-file.phel:1

1| (+ 1 2 3 unknown-symbol)
            ^^^^^^^^^^^^^^

MSG
        );
        $exceptionPrinter = $this->createTextExceptionPrinter();
        $exceptionPrinter->printException($exception, $codeSnippet);
    }

    private function createTextExceptionPrinter(): TextExceptionPrinter
    {
        return new TextExceptionPrinter(
            $this->stubExceptionArgsPrinter(),
            $this->stubColorStyle(),
            $this->stubMunge(),
            $this->stubFilePositionExtractor()
        );
    }

    private function stubExceptionArgsPrinter(): ExceptionArgsPrinterInterface
    {
        return $this->createStub(ExceptionArgsPrinterInterface::class);
    }

    private function stubColorStyle(): ColorStyleInterface
    {
        $colorStyle = $this->createStub(ColorStyleInterface::class);
        $colorStyle->method('blue')->willReturnCallback(fn (string $msg) => $msg);
        $colorStyle->method('red')->willReturnCallback(fn (string $msg) => $msg);

        return $colorStyle;
    }

    private function stubMunge(): MungeInterface
    {
        return $this->createStub(MungeInterface::class);
    }

    private function stubFilePositionExtractor(): FilePositionExtractorInterface
    {
        return $this->createStub(FilePositionExtractorInterface::class);
    }
}
