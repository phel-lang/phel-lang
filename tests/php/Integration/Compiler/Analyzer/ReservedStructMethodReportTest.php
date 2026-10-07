<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * A struct method overrides the base method of the same name. It used to
 * reach PHP as `Declaration of ...::merge($x) must be compatible`, or, for
 * `find`, silently replace the struct's own lookups (#3576). The caret
 * lands on the method name.
 */
final class ReservedStructMethodReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'method.phel';

    public function test_a_method_the_struct_base_declares_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Method merge is reserved: every struct already has a merge() method
            in method.phel:3

            3| (defstruct s [a] I (merge [this x] x))
                                   ^^^^^

            REPORT;

        self::assertSame(
            $expected,
            $this->report("(ns method.a)\n(definterface I (merge [this x]))\n(defstruct s [a] I (merge [this x] x))"),
        );
    }

    public function test_a_method_the_struct_base_declares_without_a_return_type_is_reserved_too(): void
    {
        self::assertStringContainsString(
            '[PHEL007] Method find is reserved: every struct already has a find() method',
            $this->report("(ns method.b)\n(definterface J (find [this x]))\n(defstruct s [a] J (find [this x] x))"),
        );
    }

    public function test_a_php_block_method_the_struct_base_declares_is_reserved(): void
    {
        self::assertStringContainsString(
            '[PHEL007] Method find is reserved: every struct already has a find() method',
            $this->report("(ns method.d)\n(defstruct* s [a] :php (find [this x] x))"),
        );
    }

    public function test_a_php_block_magic_method_can_be_overridden(): void
    {
        $this->expectNotToPerformAssertions();

        $this->compilerFacade->compile(
            "(ns method.e)\n(defstruct* s [a] :php (__invoke [this x] x) (__toString [this] \"s\"))",
            new CompileOptions()->setSource(self::SOURCE),
        );
    }

    public function test_an_interface_the_struct_base_implements_can_be_overridden(): void
    {
        $this->expectNotToPerformAssertions();

        $this->compilerFacade->compile(
            "(ns method.c)\n(defstruct s [a] \\Stringable (__toString [this] \"s\"))",
            new CompileOptions()->setSource(self::SOURCE),
        );
    }

    public function test_a_method_inherited_from_an_interface_the_struct_base_implements_can_be_overridden(): void
    {
        $this->expectNotToPerformAssertions();

        $this->compilerFacade->compile(
            "(ns method.f)\n(defstruct s [a] PhelTest.Integration.Compiler.Analyzer.Fixtures.NamedStringable (__toString [this] \"s\") (name [this] \"n\"))",
            new CompileOptions()->setSource(self::SOURCE),
        );
    }

    private function report(string $phelCode): string
    {
        try {
            $this->compilerFacade->compile($phelCode, new CompileOptions()->setSource(self::SOURCE));
        } catch (CompilerException $compilerException) {
            return $this->exceptionReport(
                $compilerException->getNestedException(),
                $compilerException->getCodeSnippet(),
            );
        }

        self::fail('Expected the compiler to reject: ' . $phelCode);
    }
}
