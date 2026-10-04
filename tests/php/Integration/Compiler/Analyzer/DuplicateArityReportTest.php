<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * Each arity compiles to one PHP method, so an arity declared twice used to
 * reach PHP as `Cannot redeclare ...::invokeArity1()`, a fatal error with no
 * Phel location (#3534). The caret lands on the second parameter vector.
 */
final class DuplicateArityReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'arity.phel';

    public function test_a_defn_with_one_arity_twice_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Can't have 2 overloads with the same arity
            in arity.phel:2

            2| (defn f ([x] :a) ([y] :b))
                                 ^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns arity.a)\n(defn f ([x] :a) ([y] :b))"));
    }

    public function test_a_fn_with_one_arity_twice_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Can't have 2 overloads with the same arity
            in arity.phel:2

            2| (fn ([] 1) ([a b] 2) ([c d] 3))
                                     ^^^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns arity.b)\n(fn ([] 1) ([a b] 2) ([c d] 3))"));
    }

    public function test_a_defmacro_with_one_arity_twice_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Can't have 2 overloads with the same arity
            in arity.phel:2

            2| (defmacro m ([x] x) ([y] y))
                                    ^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns arity.c)\n(defmacro m ([x] x) ([y] y))"));
    }

    public function test_a_protocol_method_with_one_arity_twice_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Can't have 2 overloads with the same arity
            in arity.phel:2

            2| (defprotocol P (m [this] [that]))
                                        ^^^^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns arity.d)\n(defprotocol P (m [this] [that]))"));
    }

    public function test_an_extend_type_impl_with_one_arity_twice_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Can't have 2 overloads with the same arity
            in arity.phel:3

            3| (extend-type :int P (m [x] 1) (m [y] 2))
                                                ^^^

            REPORT;

        $phelCode = "(ns arity.e)\n(defprotocol P (m [this]))\n(extend-type :int P (m [x] 1) (m [y] 2))";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_a_defrecord_impl_with_one_arity_twice_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Can't have 2 overloads with the same arity
            in arity.phel:3

            3| (defrecord R [a] P (m [x] 1) (m [y] 2))
                                               ^^^

            REPORT;

        $phelCode = "(ns arity.f)\n(defprotocol P (m [this]))\n(defrecord R [a] P (m [x] 1) (m [y] 2))";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_two_variadic_overloads_name_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Variadic overload must be the last one
            in arity.phel:2

            2| (defn f ([& xs] :a) ([& ys] :b))
                       ^^^^^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns arity.g)\n(defn f ([& xs] :a) ([& ys] :b))"));
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
