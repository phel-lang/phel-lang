<?php

declare(strict_types=1);

namespace PhelTest\Integration\Printer;

use Gacela\Config;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Printer\Printer;
use Phel\Runtime\RuntimeSingleton;
use PHPUnit\Framework\TestCase;

final class PrinterTest extends TestCase
{
    private CompilerFacadeInterface $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Config::setApplicationRootDir(__DIR__);
        RuntimeSingleton::reset();
    }

    public function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
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
        $tokenStream = $this->compilerFacade->createLexer()->lexString($string);
        $parseTree = $this->compilerFacade->parseNext($tokenStream);

        return (string)$this->compilerFacade->read($parseTree)->getAst();
    }
}
