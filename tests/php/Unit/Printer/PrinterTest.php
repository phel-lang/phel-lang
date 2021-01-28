<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\CompilerFactory;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class PrinterTest extends TestCase
{
    private CompilerFactory $compilerFactory;

    public function setUp(): void
    {
        $this->compilerFactory = new CompilerFactory();
    }

    public function testPrintString(): void
    {
        self::assertEquals(
            '"test"',
            $this->print('test')
        );
    }

    public function testPrintEscapedStringChars(): void
    {
        self::assertEquals(
            '"\n\r\t\v\f\e\"\$\\\\"',
            $this->print("\n\r\t\v\f\e\"\$\\")
        );
    }

    public function testPrintDollarSignEscapedStringChars(): void
    {
        self::assertEquals(
            '"\$ \$abc"',
            $this->print($this->read('"$ $abc"'))
        );
    }

    public function testPrintEscapedHexdecimalChars(): void
    {
        self::assertEquals(
            '"\x07"',
            $this->print("\x07")
        );
    }

    public function testPrintEscapedUnicodeChars(): void
    {
        self::assertEquals(
            '"\u{1000}"',
            $this->print("\u{1000}")
        );
    }

    public function testPrintZero(): void
    {
        self::assertEquals(
            '0',
            $this->print(0)
        );
    }

    public function testPrintToStringFromObject(): void
    {
        $class = new class() {
            public function __toString(): string
            {
                return 'toString method';
            }
        };

        self::assertSame('toString method', $this->print($class));
    }

    private function print($x): string
    {
        return Printer::readable()->print($x);
    }

    private function read(string $string): string
    {
        $parser = $this->compilerFactory->createParser();
        $reader = $this->compilerFactory->createReader(new GlobalEnvironment());
        $tokenStream = $this->compilerFactory->createLexer()->lexString($string);
        $parseTree = $parser->parseNext($tokenStream);

        return (string)$reader->read($parseTree)->getAst();
    }
}
