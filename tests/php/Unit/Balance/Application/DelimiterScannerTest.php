<?php

declare(strict_types=1);

namespace PhelTest\Unit\Balance\Application;

use Gacela\Framework\Gacela;
use Generator;
use Phel\Balance\Application\DelimiterScanner;
use Phel\Compiler\CompilerFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

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

    /**
     * Appending at the end would compile, with `g` becoming a closure inside
     * `f` instead of a top-level definition. A silent change of meaning is
     * worse than the compile error it replaced.
     */
    public function test_it_refuses_to_repair_when_a_new_top_level_form_follows_the_unclosed_level(): void
    {
        $code = "(ns midfile)\n\n(defn f [x]\n  (+ x 1)\n\n(defn g [y]\n  (+ y 2))\n\n(println (g 10))\n";

        $report = $this->scanner->scan($code, 'test.phel');

        self::assertFalse($report->isRepairable());
        self::assertSame(6, $report->topLevelFormLineAfterUnclosed);
        self::assertStringContainsString('belongs before line 6', (string) $report->unrepairableReason());
    }

    /**
     * A file with a missing closer is usually mid-edit, so the follow-up form
     * is often still indented. The column-0 reading alone repaired these and
     * nested the definition inside the unclosed one, which lints clean.
     */
    public function test_it_refuses_when_an_indented_definition_follows_the_unclosed_level(): void
    {
        $code = "(ns midfile)\n\n(defn f [x]\n  (+ x 1)\n\n  (defn g [y] y)\n";

        $report = $this->scanner->scan($code, 'test.phel');

        self::assertFalse($report->isRepairable());
        self::assertSame(6, $report->topLevelFormLineAfterUnclosed);
    }

    public function test_it_refuses_when_a_reader_prefixed_definition_follows_the_unclosed_level(): void
    {
        // The `'` sits at column 0, so its `(` is at column 1.
        $code = "(ns midfile)\n\n(defn f [x]\n  (+ x 1)\n\n'(defn g [y] y)\n";

        $report = $this->scanner->scan($code, 'test.phel');

        self::assertFalse($report->isRepairable());
        self::assertSame(6, $report->topLevelFormLineAfterUnclosed);
    }

    public function test_an_indented_call_is_not_a_new_top_level_form(): void
    {
        // Only a definition head reads as top-level at an indent; an ordinary
        // call is exactly what the unclosed level is still collecting.
        $code = "(ns app)\n\n(defn a [x]\n  (+ x 1)\n  (str x)\n";

        $report = $this->scanner->scan($code, 'test.phel');

        self::assertTrue($report->isRepairable(), (string) $report->unrepairableReason());
        self::assertNull($report->topLevelFormLineAfterUnclosed);
        self::assertSame(')', $report->missingClosers());
    }

    /**
     * With no character after it the char rule does not match, so the `\` falls
     * through to the atom rule. An appended `)` becomes its character: the file
     * stays unbalanced while the run reports a repair.
     */
    public function test_it_refuses_a_trailing_character_literal_prefix(): void
    {
        $report = $this->scanner->scan("(ns t)\n\n(def c \\\n", 'test.phel');

        self::assertFalse($report->isRepairable());
        self::assertSame('\\', $report->danglingPrefixToken);
    }

    public function test_a_complete_character_literal_does_not_block_a_repair(): void
    {
        $report = $this->scanner->scan("(ns t)\n\n(def c \\a\n", 'test.phel');

        self::assertNull($report->danglingPrefixToken);
        self::assertTrue($report->isRepairable(), (string) $report->unrepairableReason());
    }

    public function test_it_still_repairs_when_the_unclosed_level_is_the_last_top_level_form(): void
    {
        $code = "(ns app)\n\n(defn a [x]\n  (+ x 1))\n\n(defn c [z]\n  (str z)\n";

        $report = $this->scanner->scan($code, 'test.phel');

        self::assertTrue($report->isRepairable(), (string) $report->unrepairableReason());
        self::assertNull($report->topLevelFormLineAfterUnclosed);
        self::assertSame(')', $report->missingClosers());
    }

    /**
     * Each of these reads the form after it, so an appended closer becomes that
     * form and the file stops parsing even though the delimiters now count out.
     */
    #[DataProvider('readerPrefixProvider')]
    public function test_it_refuses_to_repair_a_file_ending_in_a_reader_prefix(string $prefix): void
    {
        $report = $this->scanner->scan("(defn f [x]\n  (+ x 1)\n" . $prefix, 'test.phel');

        self::assertFalse($report->isRepairable(), sprintf('a trailing %s must not be repaired', $prefix));
        self::assertSame($prefix, $report->danglingPrefixToken);
        self::assertStringContainsString('reads the next form', (string) $report->unrepairableReason());
    }

    public static function readerPrefixProvider(): Generator
    {
        yield 'quote' => ["'"];
        yield 'quasiquote' => ['`'];
        yield 'unquote' => ['~'];
        yield 'unquote-splicing' => ['~@'];
        yield 'caret' => ['^'];
        yield 'deref' => ['@'];
        yield 'var-quote' => ["#'"];
        yield 'form-skip' => ['#_'];
        yield 'tagged literal' => ['#uuid'];
    }

    public function test_a_reader_prefix_with_its_form_does_not_block_a_repair(): void
    {
        $report = $this->scanner->scan("(def x '(1 2\n", 'test.phel');

        self::assertNull($report->danglingPrefixToken, 'the quote already has its form, so appending is safe');
        self::assertTrue($report->isRepairable());
        self::assertSame('))', $report->missingClosers());
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
