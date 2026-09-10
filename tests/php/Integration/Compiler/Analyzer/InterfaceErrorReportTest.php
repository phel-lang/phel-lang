<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * Pins what an unusable interface in `defstruct`/`definterface` prints:
 * PHEL009, the wording, and the line and column the caret lands on.
 */
final class InterfaceErrorReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'interface.phel';

    public function test_an_unresolvable_interface_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] Can not resolve interface NoSuchInterface
            in interface.phel:1

            1| (defstruct point [x] NoSuchInterface)
               ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(defstruct point [x] NoSuchInterface)'));
    }

    public function test_a_struct_missing_an_interface_method_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] Missing method for interface \user\Shape in defstruct
            in interface.phel:2

            2| (defstruct point [x] Shape)
               ^^^^^^^^^^^^^^^^^^^^^^^^^^^

            REPORT;

        $phelCode = "(definterface Shape (draw [this]))\n(defstruct point [x] Shape)";

        self::assertSame($expected, $this->report($phelCode));
    }

    /**
     * `(bar)` has no argument vector. Reading position 1 off the list threw an
     * out-of-bounds error from `PersistentList` before the shape check could
     * run, so a malformed method form reported as `[PHEL403] Index out of
     * bounds` at a Phel runtime class, with no snippet and no caret.
     */
    public function test_a_method_form_with_no_argument_vector_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] Method arguments must be vectors
            in interface.phel:1

            1| (definterface Shape (draw))
                                   ^^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(definterface Shape (draw))'));
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
