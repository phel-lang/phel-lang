<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Lexer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * A lexer error reports like every other compile error: `[PHEL310]`, the
 * user's file and line, the offending line, and a caret one character wide
 * under the byte the lexer stopped on.
 *
 * It used to be a bare `RuntimeException`, so the CLI printed the exception
 * class, pointed its `at` line at `LexerValueException.php`, and showed no
 * snippet at all (#3289).
 */
final class LexerErrorReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'lexer.phel';

    public function test_a_stray_hash_names_the_code_and_points_at_it(): void
    {
        $expected = <<<'REPORT'
            [PHEL310] Cannot lex '#': no token starts with it.
            in lexer.phel:1

            1| (inc #)
                    ^

            REPORT;

        self::assertSame($expected, $this->report('(inc #)'));
    }

    public function test_the_caret_lands_on_the_line_the_character_is_on(): void
    {
        $expected = <<<'REPORT'
            [PHEL310] Cannot lex '#': no token starts with it.
            in lexer.phel:3

            3| (dec #)
                    ^

            REPORT;

        self::assertSame($expected, $this->report("(inc 1)\n(inc 2)\n(dec #)"));
    }

    /**
     * The caret counts code points, not bytes, so a multibyte character
     * earlier on the line does not push it off the character it points at.
     */
    public function test_the_caret_counts_code_points_not_bytes(): void
    {
        $expected = <<<'REPORT'
            [PHEL310] Cannot lex '#': no token starts with it.
            in lexer.phel:1

            1| (inc "☃☃" #)
                         ^

            REPORT;

        self::assertSame($expected, $this->report('(inc "☃☃" #)'));
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
