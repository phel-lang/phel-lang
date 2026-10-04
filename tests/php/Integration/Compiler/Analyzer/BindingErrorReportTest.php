<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * Pins what a malformed `let`/`loop` binding vector prints: PHEL008, the
 * wording, and the line and column the caret lands on.
 */
final class BindingErrorReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'binding.phel';

    public function test_an_odd_binding_vector_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL008] Bindings must be a even number of parameters
            in binding.phel:1

            1| (let [a 1 b] a)
               ^^^^^^^^^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(let [a 1 b] a)'));
    }

    public function test_a_binding_list_instead_of_a_vector_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL008] Binding parameter must be a vector
            in binding.phel:1

            1| (let (a 1) a)
               ^^^^^^^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(let (a 1) a)'));
    }

    /**
     * `binding` expands to one var assignment per target, and the emitter
     * asserted the target was a var: a local or a PHP constant failed with an
     * `AssertionError` and half-emitted PHP (#3534).
     */
    public function test_binding_a_local_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL008] a is not a var: binding and with-redefs rebind a var defined with def
            in binding.phel:1

            1| (let [a 1] (binding [a 10] a))
                                    ^

            REPORT;

        self::assertSame($expected, $this->report('(let [a 1] (binding [a 10] a))'));
    }

    public function test_binding_a_php_constant_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL008] php/PHP_EOL is not a var: binding and with-redefs rebind a var defined with def
            in binding.phel:1

            1| (with-redefs [php/PHP_EOL 10] 1)
                             ^^^^^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(with-redefs [php/PHP_EOL 10] 1)'));
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
