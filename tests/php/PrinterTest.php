<?php

namespace Phel;

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

    private function print($x) {
        $p = new Printer();
        return $p->print($x, true);
    }
}