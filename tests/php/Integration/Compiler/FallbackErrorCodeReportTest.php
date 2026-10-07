<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Support\RendersExceptionReportTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A compile error with no more specific code prints its phase's fallback
 * code, the one `phel analyze` reports for it (#3537).
 */
final class FallbackErrorCodeReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'fallback.phel';

    public static function providerUncodedErrors(): iterable
    {
        yield 'special form with too few arguments' => [
            '(if)',
            <<<'REPORT'
                [PHEL007] 'if requires two or three arguments
                in fallback.phel:1

                1| (if)
                   ^^^^

                REPORT,
        ];

        yield 'keyword with an unknown alias' => [
            '::nope/foo',
            <<<'REPORT'
                [PHEL120] Can not resolve alias 'nope' in keyword: ::nope/foo
                in fallback.phel:1

                1| ::nope/foo
                   ^^^^^^^^^^

                REPORT,
        ];

        yield 'odd-length map literal' => [
            '{:a}',
            <<<'REPORT'
                [PHEL210] Maps must have an even number of parameters
                in fallback.phel:1

                1| {:a}
                   ^^^^

                REPORT,
        ];

        yield 'octal escape out of range' => [
            '(println "\777")',
            <<<'REPORT'
                [PHEL120] Octal escape sequence out of range: \777 is above \377.
                in fallback.phel:1

                1| (println "\777")
                            ^^^^^^

                REPORT,
        ];
    }

    #[DataProvider('providerUncodedErrors')]
    public function test_the_report_opens_with_the_code_analyze_reports(string $phelCode, string $expected): void
    {
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
