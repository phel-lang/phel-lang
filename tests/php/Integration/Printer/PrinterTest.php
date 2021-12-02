<?php

declare(strict_types=1);

namespace PhelTest\Integration\Printer;

use Gacela\Framework\Config;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class PrinterTest extends TestCase
{
    private CompilerFacadeInterface $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Config::getInstance()->setApplicationRootDir(__DIR__);
        GlobalEnvironmentSingleton::reset();
    }

    public function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
    }

    public function test_print_string(): void
    {
        self::assertEquals(
            '"test"',
            $this->print('test')
        );
    }

    public function test_print_escaped_string_chars(): void
    {
        self::assertEquals(
            '"\n\r\t\v\f\e\"\$\\\\"',
            $this->print("\n\r\t\v\f\e\"\$\\")
        );
    }

    public function test_print_dollar_sign_escaped_string_chars(): void
    {
        self::assertEquals(
            '"\$ \$abc"',
            $this->print($this->read('"$ $abc"'))
        );
    }

    public function test_print_escaped_hexdecimal_chars(): void
    {
        self::assertEquals(
            '"\x07"',
            $this->print("\x07")
        );
    }

    public function test_print_escaped_unicode_chars(): void
    {
        self::assertEquals(
            '"\u{1000}"',
            $this->print("\u{1000}")
        );
    }

    public function test_print_zero(): void
    {
        self::assertEquals(
            '0',
            $this->print(0)
        );
    }

    public function test_print_to_string_from_object(): void
    {
        $class = new class () {
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
        $tokenStream = $this->compilerFacade->lexString($string);
        $parseTree = $this->compilerFacade->parseNext($tokenStream);

        return (string)$this->compilerFacade->read($parseTree)->getAst();
    }
}
