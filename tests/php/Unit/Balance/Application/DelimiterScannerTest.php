<?php

declare(strict_types=1);

namespace PhelTest\Unit\Balance\Application;

use Gacela\Framework\Gacela;
use Phel\Balance\Application\DelimiterScanner;
use Phel\Compiler\CompilerFacade;
use PHPUnit\Framework\TestCase;

final class DelimiterScannerTest extends TestCase
{
    private DelimiterScanner $scanner;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
        $this->scanner = new DelimiterScanner(new CompilerFacade());
    }

    public function test_it_reports_balanced_source_as_balanced(): void
    {
        $report = $this->scanner->scan("(defn f [x] {:k [x 1]})\n", 'test.phel');

        self::assertTrue($report->isBalanced());
        self::assertFalse($report->isRepairable());
        self::assertSame('', $report->missingClosers());
    }

    /**
     * `\(` is a single-character string literal, not an open paren. A byte
     * counter reports this balanced file as three levels deep.
     */
    public function test_it_ignores_delimiters_that_are_character_literals(): void
    {
        $report = $this->scanner->scan('(str \\( \\) \\[ \\] \\{ \\})', 'test.phel');

        self::assertTrue($report->isBalanced());
    }

    public function test_it_ignores_delimiters_inside_strings(): void
    {
        $report = $this->scanner->scan('(str "((( [[[ {{{" "\\" ((")', 'test.phel');

        self::assertTrue($report->isBalanced());
    }

    public function test_it_ignores_delimiters_inside_line_comments(): void
    {
        $report = $this->scanner->scan("(str x) ; ( [ { unclosed on purpose\n", 'test.phel');

        self::assertTrue($report->isBalanced());
    }

    public function test_it_ignores_delimiters_inside_regex_literals(): void
    {
        $report = $this->scanner->scan('(re-find #"^(a|b)[c]{2}$" s)', 'test.phel');

        self::assertTrue($report->isBalanced());
    }

    /**
     * `#(`, `#?(` and `#?@(` swallow their `(` into one token and close with a
     * plain `)`, so opener text and closer text are not mirror images.
     */
    public function test_it_matches_hash_openers_against_their_plain_closers(): void
    {
        $report = $this->scanner->scan('(list #(inc %) #{1 2} #?(:php 1) #?@(:php [1]))', 'test.phel');

        self::assertTrue($report->isBalanced(), 'hash openers should reconcile against ) and }');
    }

    public function test_it_reports_an_unclosed_hash_fn_as_needing_a_paren(): void
    {
        $report = $this->scanner->scan('(map #(inc %', 'test.phel');

        self::assertTrue($report->isRepairable());
        self::assertSame('))', $report->missingClosers());
        self::assertSame('#(', $report->unclosed[1]->openerText);
        self::assertSame(')', $report->unclosed[1]->closerText);
    }

    public function test_it_reports_unclosed_levels_outermost_first_with_positions(): void
    {
        $report = $this->scanner->scan("(defn f [x]\n  (str x\n", 'test.phel');

        self::assertCount(2, $report->unclosed);
        self::assertSame(1, $report->unclosed[0]->line);
        self::assertSame(0, $report->unclosed[0]->column);
        self::assertSame(2, $report->unclosed[1]->line);
        self::assertSame(2, $report->unclosed[1]->column);
    }

    public function test_it_orders_missing_closers_innermost_first(): void
    {
        $report = $this->scanner->scan('(foo [bar {:k 1', 'test.phel');

        self::assertSame('}])', $report->missingClosers());
    }

    public function test_it_refuses_to_repair_a_surplus_closer(): void
    {
        $report = $this->scanner->scan('(foo))', 'test.phel');

        self::assertFalse($report->isBalanced());
        self::assertFalse($report->isRepairable());
        self::assertStringContainsString("')' closes nothing", (string) $report->unrepairableReason());
    }

    public function test_it_refuses_to_repair_a_mismatched_closer(): void
    {
        $report = $this->scanner->scan("(defn bad [x]\n  (foo]\n", 'test.phel');

        self::assertFalse($report->isRepairable());
        self::assertStringContainsString("']' closes '('", (string) $report->unrepairableReason());
    }

    /**
     * The atom rule swallows an unclosed `"`, so the `)` the author meant as
     * string content lexes as a real closer. The imbalance the stack then
     * reports is a phantom and appending to it writes a differently broken file.
     */
    public function test_it_refuses_to_repair_an_unterminated_string(): void
    {
        $report = $this->scanner->scan("(println \"hi) (there\n", 'test.phel');

        self::assertFalse($report->isBalanced());
        self::assertFalse($report->isRepairable());
        self::assertSame(1, $report->unterminatedStringLine);
        self::assertStringContainsString('unterminated string literal on line 1', (string) $report->unrepairableReason());
    }

    public function test_it_flags_a_trailing_line_comment(): void
    {
        $report = $this->scanner->scan("(str x\n;; tidy later\n", 'test.phel');

        self::assertTrue($report->endsInLineComment);
    }

    public function test_it_does_not_flag_a_comment_that_is_not_last(): void
    {
        $report = $this->scanner->scan("(str ; here\n  x\n", 'test.phel');

        self::assertFalse($report->endsInLineComment);
    }
}
