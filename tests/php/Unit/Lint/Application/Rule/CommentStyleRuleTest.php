<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\CommentStyleRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class CommentStyleRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_standalone_single_semicolon_comment(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("; a standalone comment\n(def x 1)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::COMMENT_STYLE, $diagnostics[0]->code);
        self::assertStringContainsString(';;', $diagnostics[0]->message);
        self::assertSame(1, $diagnostics[0]->startLine);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_indented_standalone_single_semicolon_comment(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("(defn f []\n  ; explain\n  1)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(2, $diagnostics[0]->startLine);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_standalone_double_semicolon_comment(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis(";; a standalone comment\n(def x 1)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_an_inline_comment_after_code(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("(def x 1) ; why one\n");

        self::assertSame([], $rule->apply($analysis));
    }

    /**
     * Three or more semicolons stay clean: the rule only asks that a
     * whole-line comment is not written with the inline marker. Clojure uses
     * `;;;` for section headers and Phel does not forbid it.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_three_or_more_semicolons(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis(";;; section header\n;;;; sub section\n(def x 1)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_semicolon_inside_a_string_literal(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("(def doc\n  \"usage:\n  ; => 42\")\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_semicolon_inside_a_multiline_comment(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("#|\n; not a line comment\n|#\n(def x 1)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_bare_hash_line_comment(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("# legacy comment\n(def x 1)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_every_line_of_a_single_semicolon_block(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("; first\n; second\n; third\n(def x 1)\n");

        self::assertCount(3, $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_standalone_comment_on_the_last_line_without_newline(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("(def x 1)\n; trailing");

        self::assertCount(1, $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_comment_after_a_multiline_string_ends_on_that_line(): void
    {
        $rule = new CommentStyleRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("(def s \"one\ntwo\") ; closing note\n");

        self::assertSame([], $rule->apply($analysis));
    }
}
