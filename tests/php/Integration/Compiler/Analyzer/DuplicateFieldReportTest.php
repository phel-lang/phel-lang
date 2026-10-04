<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * A struct field is a constructor parameter and a key, so a repeated one has
 * no meaning; it used to reach PHP as `Redefinition of parameter $x` (#3534).
 * The caret lands on the repeated field.
 */
final class DuplicateFieldReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'field.phel';

    public function test_a_defstruct_with_a_repeated_field_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Field x is declared more than once
            in field.phel:2

            2| (defstruct point [x y x])
                                     ^

            REPORT;

        self::assertSame($expected, $this->report("(ns field.a)\n(defstruct point [x y x])"));
    }

    public function test_a_defrecord_with_a_repeated_field_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Field x is declared more than once
            in field.phel:2

            2| (defrecord P [x x])
                               ^

            REPORT;

        self::assertSame($expected, $this->report("(ns field.b)\n(defrecord P [x x])"));
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
