<?php

namespace Phel;

use Phel\Stream\StringCharStream;
use PHPUnit\Framework\TestCase;

class PrinterTest extends TestCase {

    public function testPrintString() {
        $this->assertEquals(
            '"test"',
            $this->print("test")
        );
    }

    public function testPrintEscapedStringChars() {
        $this->assertEquals(
            '"\n\r\t\v\f\e\"\$\\\\"',
            $this->print("\n\r\t\v\f\e\"\$\\")
        );
    }

    public function testPrintDollarSignEscapedStringChars() {
        $this->assertEquals(
            '"\$ \$abc"',
            $this->print($this->read('"$ $abc"'))
        );
    }

    public function testPrintEscapedHexdecimalChars() {
        $this->assertEquals(
            '"\x07"',
            $this->print("\x07")
        );
    }

    public function testPrintEscapedUnicodeChars() {
        $this->assertEquals(
            '"\u{1000}"',
            $this->print("\u{1000}")
        );
    }

    public function testPrintZero() {
        $this->assertEquals(
            '0',
            $this->print(0)
        );
    }

    private function print($x) {
        $p = new Printer();
        return $p->print($x, true);
    }

    public function read($string) {
        $reader = new Reader();
        $lexer = new Lexer();
        $stream = new StringCharStream($string);
        $tokenStream = $lexer->lex($stream);
        
        $result = $reader->readNext($tokenStream)->getAst();

        return $result;
    }
}