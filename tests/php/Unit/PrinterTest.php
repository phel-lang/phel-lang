<?php

declare(strict_types=1);

namespace PhelTest\Unit;

use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\Lexer;
use Phel\Printer;
use Phel\Compiler\Reader;
use PHPUnit\Framework\TestCase;

final class PrinterTest extends TestCase
{
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

    private function print($x): string
    {
        return Printer::readable()->print($x);
    }

    private function read(string $string): string
    {
        $globalEnv = new GlobalEnvironment();
        $tokenStream = (new Lexer())->lexString($string);

        return (string) (new Reader($globalEnv))->readNext($tokenStream)->getAst();
    }
}
