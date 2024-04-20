<?php

declare(strict_types=1);

namespace PhelTest\Integration\Printer;

use Gacela\Framework\Gacela;
use Phel\Printer\Printer;
use Phel\Transpiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Transpiler\TranspilerFacade;
use Phel\Transpiler\TranspilerFacadeInterface;
use PHPUnit\Framework\TestCase;

final class PrinterTest extends TestCase
{
    private TranspilerFacadeInterface $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
        GlobalEnvironmentSingleton::reset();
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new TranspilerFacade();
    }

    public function test_print_string(): void
    {
        self::assertSame(
            '"test"',
            $this->print('test'),
        );
    }

    public function test_print_escaped_string_chars(): void
    {
        self::assertSame(
            '"\n\r\t\v\f\e\"\$\\\\"',
            $this->print("\n\r\t\v\f\e\"\$\\"),
        );
    }

    public function test_print_dollar_sign_escaped_string_chars(): void
    {
        self::assertSame(
            '"\$ \$abc"',
            $this->print($this->read('"$ $abc"')),
        );
    }

    public function test_print_escaped_hexadecimal_chars(): void
    {
        self::assertSame(
            '"\x07"',
            $this->print("\x07"),
        );
    }

    public function test_print_escaped_unicode_chars(): void
    {
        self::assertSame(
            '"\u{1000}"',
            $this->print("\u{1000}"),
        );
    }

    public function test_print_zero(): void
    {
        self::assertSame(
            '0',
            $this->print(0),
        );
    }

    public function test_print_to_string_from_object(): void
    {
        $class = new class() {
            public function __toString(): string
            {
                return 'toString method';
            }
        };

        self::assertSame('toString method', $this->print($class));
    }

    private function print(mixed $x): string
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
