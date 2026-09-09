<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Parser;

use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ExpressionParserFactory;
use PhelTest\Support\RendersExceptionReportTrait;
use PHPUnit\Framework\TestCase;

/**
 * Pins what a reader mistake actually prints: the error code, the wording, the
 * line the form was opened on, and the column the caret lands in.
 */
final class UnterminatedFormReportTest extends TestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'broken.phel';

    public function test_a_missing_closing_paren_names_the_line_it_was_opened_on(): void
    {
        $expected = <<<'REPORT'
            [PHEL100] Unterminated list starting at line 2. Did you forget a closing ')'?
            in broken.phel:2

            2| (defn f [x] (+ x 1)
               ^

            REPORT;

        self::assertSame($expected, $this->report("(ns repro)\n(defn f [x] (+ x 1)"));
    }

    public function test_a_missing_closing_bracket_names_the_vector(): void
    {
        $expected = <<<'REPORT'
            [PHEL101] Unterminated vector starting at line 1. Did you forget a closing ']'?
            in broken.phel:1

            1| (def v [1 2 3
                      ^

            REPORT;

        self::assertSame($expected, $this->report('(def v [1 2 3'));
    }

    public function test_a_missing_closing_brace_names_the_map(): void
    {
        $expected = <<<'REPORT'
            [PHEL102] Unterminated map starting at line 1. Did you forget a closing '}'?
            in broken.phel:1

            1| (def m {:a 1
                      ^

            REPORT;

        self::assertSame($expected, $this->report('(def m {:a 1'));
    }

    public function test_a_missing_closing_brace_names_the_set(): void
    {
        $expected = <<<'REPORT'
            [PHEL103] Unterminated set starting at line 1. Did you forget a closing '}'?
            in broken.phel:1

            1| (def s #{1 2
                      ^^

            REPORT;

        self::assertSame($expected, $this->report('(def s #{1 2'));
    }

    public function test_a_mismatched_closer_names_both_sides(): void
    {
        $expected = <<<'REPORT'
            [PHEL101] Expected ']' to close the vector opened at line 3, found ')'.
            in broken.phel:3

            1| (defn c [x] (let [y (a x)
            2|                   z (b x)]
            3|               [y z)
                                 ^

            REPORT;

        $phelCode = "(defn c [x] (let [y (a x)\n                  z (b x)]\n              [y z))";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_an_unclosed_quote_reports_the_string_and_not_the_enclosing_list(): void
    {
        $expected = <<<'REPORT'
            [PHEL301] Unterminated string starting at line 1. Did you forget a closing '"'?
            in broken.phel:1

            1| (println "unterminated
                        ^^^^^^^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(println "unterminated'));
    }

    public function test_a_closer_with_nothing_open_says_so(): void
    {
        $expected = <<<'REPORT'
            [PHEL110] Unexpected ')': there is no open form to close.
            in broken.phel:1

            1| )
               ^

            REPORT;

        self::assertSame($expected, $this->report(')'));
    }

    private function report(string $phelCode): string
    {
        $parser = new Parser(new ExpressionParserFactory(), new GlobalEnvironment());

        try {
            $parser->parseAll(new Lexer()->lexString($phelCode, self::SOURCE));
        } catch (AbstractParserException $parserException) {
            return $this->exceptionReport($parserException, $parserException->getCodeSnippet());
        }

        self::fail('Expected the parser to reject: ' . $phelCode);
    }
}
