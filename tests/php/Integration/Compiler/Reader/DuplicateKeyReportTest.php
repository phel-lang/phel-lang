<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Reader;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * Pins what a repeated constant key in a map or set literal prints. The
 * reader used to keep one of the entries and drop the other without a word.
 */
final class DuplicateKeyReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'duplicate.phel';

    public function test_a_repeated_map_key_is_a_located_reader_error(): void
    {
        $expected = <<<'REPORT'
            [PHEL203] Duplicate key: :a
            in duplicate.phel:1

            1| {:a 1 :a 2}
                     ^^

            REPORT;

        self::assertSame($expected, $this->report('{:a 1 :a 2}'));
    }

    public function test_a_repeated_set_element_is_a_located_reader_error(): void
    {
        $expected = <<<'REPORT'
            [PHEL203] Duplicate key: "b"
            in duplicate.phel:1

            1| #{:a "b" 1 "b"}
                          ^^^

            REPORT;

        self::assertSame($expected, $this->report('#{:a "b" 1 "b"}'));
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
