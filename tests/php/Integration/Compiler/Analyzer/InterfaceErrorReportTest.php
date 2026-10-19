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
            [PHEL009] Missing method for interface \user\Shape in defstruct: draw
            in interface.phel:2

            2| (defstruct point [x] Shape)
               ^^^^^^^^^^^^^^^^^^^^^^^^^^^

            REPORT;

        $phelCode = "(definterface Shape (draw [this]))\n(defstruct point [x] Shape)";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_a_struct_missing_some_interface_methods_names_them(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] Missing method for interface \interface\partial\Shape in defstruct: area, perimeter
            in interface.phel:3

            3| (defstruct point [x] Shape (draw [this] 1))
               ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

            REPORT;

        $phelCode = "(ns interface.partial)\n(definterface Shape (draw [this]) (area [this]) (perimeter [this]))\n(defstruct point [x] Shape (draw [this] 1))";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_an_implementation_with_extra_parameters_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] Method draw takes 0 parameters after this in interface \interface\extra\Shape, but this implementation takes 1
            in interface.phel:3

            3| (defstruct point [x] Shape (draw [this extra] 1))
                                          ^^^^^^^^^^^^^^^^^^^^^

            REPORT;

        $phelCode = "(ns interface.extra)\n(definterface Shape (draw [this]))\n(defstruct point [x] Shape (draw [this extra] 1))";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_an_implementation_with_missing_parameters_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] Method draw takes 1 parameter after this in interface \interface\fewer\Shape, but this implementation takes 0
            in interface.phel:3

            3| (defstruct point [x] Shape (draw [this] 1))
                                          ^^^^^^^^^^^^^^^

            REPORT;

        $phelCode = "(ns interface.fewer)\n(definterface Shape (draw [this color]))\n(defstruct point [x] Shape (draw [this] 1))";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_an_interface_method_without_parameters_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] Method draw must take at least the this parameter
            in interface.phel:2

            2| (definterface Shape (draw []))
                                   ^^^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns interface.empty)\n(definterface Shape (draw []))"));
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

    /**
     * A PHP interface method has one signature, so a second arity under the
     * same name cannot compile; it used to stop PHP with `Cannot redeclare`.
     */
    public function test_an_interface_method_declared_twice_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] definterface declares method m more than once. A PHP method has one signature: give each arity its own name.
            in interface.phel:2

            2| (definterface Twice (m [this]) (m [this x]))
                                              ^^^^^^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns interface.twice)\n(definterface Twice (m [this]) (m [this x]))"));
    }

    public function test_an_interface_method_with_a_repeated_parameter_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL009] Method m declares parameter x more than once
            in interface.phel:2

            2| (definterface Repeated (m [this x x]))
                                                 ^

            REPORT;

        self::assertSame($expected, $this->report("(ns interface.repeated)\n(definterface Repeated (m [this x x]))"));
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
