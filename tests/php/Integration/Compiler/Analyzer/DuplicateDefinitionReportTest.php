<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * Pins what a redefinition actually prints: the error code, the wording, the
 * line and column the caret lands on, and the line naming the first definition.
 */
final class DuplicateDefinitionReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'duplicate.phel';

    public function test_a_redefinition_names_the_code_the_caret_and_the_first_definition(): void
    {
        $expected = <<<'REPORT'
            [PHEL004] Symbol 'f' is already bound in namespace 'repro.b'
            in duplicate.phel:3

            3| (defn f [x y] x)
                     ^

              first defined at duplicate.phel:2

            REPORT;

        $phelCode = "(ns repro.b)\n(defn f [x] x)\n(defn f [x y] x)";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_a_redefinition_of_a_def_underlines_the_whole_name(): void
    {
        $expected = <<<'REPORT'
            [PHEL004] Symbol 'answer' is already bound in namespace 'repro.c'
            in duplicate.phel:3

            3| (def answer 43)
                    ^^^^^^

              first defined at duplicate.phel:2

            REPORT;

        $phelCode = "(ns repro.c)\n(def answer 42)\n(def answer 43)";

        self::assertSame($expected, $this->report($phelCode));
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
