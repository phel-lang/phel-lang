<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Reader;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * Pins what `~@` outside a collection prints. It used to escape the compiler
 * as a bare `SpliceNotInListException` with no location at all.
 */
final class InvalidSpliceReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'splice.phel';

    public function test_a_splice_outside_a_collection_is_a_located_reader_error(): void
    {
        $expected = <<<'REPORT'
            [PHEL202] Unquote-splicing (~@) is only valid inside a collection within a quasiquote
            in splice.phel:1

            1| `~@xs
                ^^^^

            REPORT;

        self::assertSame($expected, $this->report('`~@xs'));
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
