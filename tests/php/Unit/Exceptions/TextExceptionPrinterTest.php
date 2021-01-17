<?php

declare(strict_types=1);

namespace PhelTest\Unit\Exceptions;

use Phel\Command\Repl\ColorStyleInterface;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Exceptions\PhelCodeException;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\Lang\SourceLocation;
use Phel\Printer\PrinterInterface;
use PHPUnit\Framework\TestCase;

final class TextExceptionPrinterTest extends TestCase
{
    public function testPrintException(): void
    {
        $file = 'example-file.phel';

        $codeSnippet = new CodeSnippet(
            $startLocation = new SourceLocation($file, $line = 1, $column = 1),
            $endLocation = new SourceLocation($file, $line = 1, $column = 3),
            '(+ 1 2 3 unknown-symbol)'
        );

        $exception = new PhelCodeException(
            'Example code exception message',
            $startLocation = new SourceLocation($file, $line = 1, $column = 9),
            $endLocation = new SourceLocation($file, $line = 1, $column = 23),
        );

        $this->expectOutputString(<<<'MSG'
Example code exception message
in example-file.phel:1

1| (+ 1 2 3 unknown-symbol)
            ^^^^^^^^^^^^^^

MSG);
        $exceptionPrinter = $this->createTextExceptionPrinter();
        $exceptionPrinter->printException($exception, $codeSnippet);
    }

    private function createTextExceptionPrinter(): TextExceptionPrinter
    {
        return new TextExceptionPrinter(
            $this->stubPrinter(),
            $this->stubColorStyle(),
            $this->stubMunge()
        );
    }

    private function stubPrinter(): PrinterInterface
    {
        return $this->createStub(PrinterInterface::class);
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
}
