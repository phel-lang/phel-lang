<?php

declare(strict_types=1);

namespace PhelTest\Integration\Printer;

use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Atom;
use Phel\Phel;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Printer\Printer;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function dirname;

final class PrinterTest extends TestCase
{
    private CompilerFacadeInterface $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
        GlobalEnvironmentSingleton::reset();
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
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
            '"က"',
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

    public function test_print_string_with_color(): void
    {
        self::assertSame(
            "\033[0;95m\"test\"\033[0m",
            Printer::readableWithColor()->print('test'),
        );
    }

    public function test_eval_survives_printing_a_cyclic_atom(): void
    {
        $root = dirname(__DIR__, 4);
        $result = Subprocess::run(
            [PHP_BINARY, '-d', 'memory_limit=256M', '-d', 'max_execution_time=30', $root . '/bin/phel',
                'eval', '(let [a (atom nil)] (reset! a a))'],
            $root,
            env: [...getenv(), 'PHEL_NO_OPCACHE_REEXEC' => '1'],
        );

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertSame("(atom #<cycle>)\n", $result->stdout);
    }

    public function test_print_mutually_cyclic_atoms_through_collections(): void
    {
        $first = new Atom(null, null);
        $second = new Atom(null, null);
        $first->set(\Phel::vector([$second]));
        $second->set(\Phel::map(\Phel::keyword('back'), $first));

        self::assertSame('(atom [(atom {:back #<cycle>})])', Printer::readable()->print($first));
        self::assertSame('(atom [(atom {:back #<cycle>})])', Printer::nonReadable()->print($first));
        self::assertStringContainsString('#<cycle>', Printer::readableWithColor()->print($first));
    }

    public function test_shared_atoms_are_printed_at_each_occurrence(): void
    {
        $atom = new Atom(null, 1);
        $printer = Printer::readable();
        $values = \Phel::vector([$atom, $atom]);

        self::assertSame('[(atom 1) (atom 1)]', $printer->print($values));
        self::assertSame('[(atom 1) (atom 1)]', $printer->print($values));
    }

    public function test_atom_print_path_is_cleared_after_an_exception(): void
    {
        $printer = Printer::readable();
        $atom = new Atom(null, new class() {
            public function __toString(): string
            {
                throw new RuntimeException('cannot print');
            }
        });

        try {
            $printer->print($atom);
            self::fail('Expected printing to throw');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('cannot print', $runtimeException->getMessage());
        }

        $atom->set(2);
        self::assertSame('(atom 2)', $printer->print($atom));
    }

    private function print(mixed $x): string
    {
        return Printer::readable()->print($x);
    }

    private function read(string $string): string
    {
        $tokenStream = $this->compilerFacade->lexString($string);
        $parseTree = $this->compilerFacade->parseNext($tokenStream);

        return (string) $this->compilerFacade->read($parseTree)->getAst();
    }
}
