<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * A parameter list written as a list used to reach `apply` inside `defn` and
 * fail with `apply final argument must be nil, string, array, or Traversable`
 * (#3537).
 */
final class ParameterDeclarationReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'params.phel';

    public function test_a_defn_with_a_list_as_parameters_says_it_must_be_a_vector(): void
    {
        self::assertStringStartsWith(
            "[PHEL005] Error in expanding macro \"phel.core/defn\"\n"
            . "  Expanding: (defn f (a b) a)\n"
            . '  Cause: Parameter declaration of f must be a vector',
            $this->report("(ns params.a)\n(defn f (a b) a)"),
        );
    }

    public function test_a_defn_with_a_symbol_as_parameters_says_it_must_be_a_vector(): void
    {
        self::assertStringStartsWith(
            "[PHEL005] Error in expanding macro \"phel.core/defn\"\n"
            . "  Expanding: (defn f a)\n"
            . '  Cause: Parameter declaration of f must be a vector',
            $this->report("(ns params.b)\n(defn f a)"),
        );
    }

    public function test_a_defn_with_a_docstring_and_a_list_as_parameters_says_it_must_be_a_vector(): void
    {
        self::assertStringStartsWith(
            "[PHEL005] Error in expanding macro \"phel.core/defn\"\n"
            . "  Expanding: (defn f \"doc\" (a b) a)\n"
            . '  Cause: Parameter declaration of f must be a vector',
            $this->report("(ns params.c)\n(defn f \"doc\" (a b) a)"),
        );
    }

    public function test_a_multi_arity_defn_with_a_list_as_parameters_says_it_must_be_a_vector(): void
    {
        self::assertStringStartsWith(
            "[PHEL005] Error in expanding macro \"phel.core/defn\"\n"
            . "  Expanding: (defn f ([a] a) (b c))\n"
            . '  Cause: Parameter declaration of f must be a vector',
            $this->report("(ns params.d)\n(defn f ([a] a) (b c))"),
        );
    }

    public function test_a_defmacro_with_a_list_as_parameters_says_it_must_be_a_vector(): void
    {
        self::assertStringStartsWith(
            "[PHEL005] Error in expanding macro \"phel.core/defmacro\"\n"
            . "  Expanding: (defmacro m (a) a)\n"
            . '  Cause: Parameter declaration of m must be a vector',
            $this->report("(ns params.e)\n(defmacro m (a) a)"),
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
