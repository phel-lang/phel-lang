<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * PHP allows `void` and `never` only as return types. A param tagged with one
 * used to stop PHP with `void cannot be used as a parameter type` (#3534).
 */
final class ReturnOnlyParamTagReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'tag.phel';

    public function test_a_void_param_names_the_code_at_the_param(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Parameter s cannot be tagged ^void: PHP allows void only as a return type
            in tag.phel:2

            2| (defn f [^void s] s)
                              ^

            REPORT;

        self::assertSame($expected, $this->report("(ns tag.a)\n(defn f [^void s] s)"));
    }

    public function test_a_never_param_names_the_code(): void
    {
        self::assertStringContainsString(
            '[PHEL007] Parameter s cannot be tagged ^never: PHP allows never only as a return type',
            $this->report("(ns tag.b)\n(fn [a ^never s] s)"),
        );
    }

    public function test_void_stays_valid_as_a_return_type(): void
    {
        $this->compilerFacade->compile("(ns tag.c)\n(defn f ^void [s] (println s))", new CompileOptions()->setSource(self::SOURCE));

        $this->expectNotToPerformAssertions();
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
