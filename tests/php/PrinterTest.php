<?php

declare(strict_types=1);

namespace Phel;

use PHPUnit\Framework\TestCase;

final class PrinterTest extends TestCase
{
    public function testPrintString(): void
    {
        $this->assertEquals(
            '"test"',
            $this->print('test')
        );
    }

    public function testPrintEscapedStringChars(): void
    {
        $this->assertEquals(
            '"\n\r\t\v\f\e\"\$\\\\"',
            $this->print("\n\r\t\v\f\e\"\$\\")
        );
    }

    public function testPrintDollarSignEscapedStringChars(): void
    {
        $this->assertEquals(
            '"\$ \$abc"',
            $this->print($this->read('"$ $abc"'))
        );
    }

    public function testPrintEscapedHexdecimalChars(): void
    {
        $this->assertEquals(
            '"\x07"',
            $this->print("\x07")
        );
    }

    public function testPrintEscapedUnicodeChars(): void
    {
        $this->assertEquals(
            '"\u{1000}"',
            $this->print("\u{1000}")
        );
    }

    public function testPrintZero(): void
    {
        $this->assertEquals(
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
