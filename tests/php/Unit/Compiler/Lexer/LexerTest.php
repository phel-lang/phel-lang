<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Lexer;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    private CompilerFactory $compilerFactory;

    protected function setUp(): void
    {
        $this->compilerFactory = new CompilerFactory();
    }

    public function test_whitespace_with_newline(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_WHITESPACE, " \t", new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 2)),
                new Token(Token::T_NEWLINE, "\r\n", new SourceLocation('string', 1, 2), new SourceLocation('string', 2, 0)),
                new Token(Token::T_WHITESPACE, '  ', new SourceLocation('string', 2, 0), new SourceLocation('string', 2, 2)),
                new Token(Token::T_NEWLINE, "\n", new SourceLocation('string', 2, 2), new SourceLocation('string', 3, 0)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 3, 0), new SourceLocation('string', 3, 0)),
            ],
            $this->lex(" \t\r\n  \n"),
        );
    }

    public function test_read_comment_without_text(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, '#', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 1)),
            ],
            $this->lex('#'),
        );
    }

    public function test_read_comment_without_new_line(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, '# Mein Kommentar', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 16)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 16), new SourceLocation('string', 1, 16)),
            ],
            $this->lex('# Mein Kommentar'),
        );
    }

    public function test_read_comment_with_new_line(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, "# Mein Kommentar\n", new SourceLocation('string', 1, 0), new SourceLocation('string', 2, 0)),
                new Token(Token::T_COMMENT, '# Mein andere Kommentar', new SourceLocation('string', 2, 0), new SourceLocation('string', 2, 23)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 2, 23), new SourceLocation('string', 2, 23)),
            ],
            $this->lex("# Mein Kommentar\n# Mein andere Kommentar"),
        );
    }

    public function test_read_semicolon_comment_without_text(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, ';', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 1)),
            ],
            $this->lex(';'),
        );
    }

    public function test_read_semicolon_comment_without_new_line(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, '; Mein Kommentar', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 16)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 16), new SourceLocation('string', 1, 16)),
            ],
            $this->lex('; Mein Kommentar'),
        );
    }

    public function test_read_semicolon_comment_with_new_line(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, "; Mein Kommentar\n", new SourceLocation('string', 1, 0), new SourceLocation('string', 2, 0)),
                new Token(Token::T_COMMENT, '; Mein andere Kommentar', new SourceLocation('string', 2, 0), new SourceLocation('string', 2, 23)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 2, 23), new SourceLocation('string', 2, 23)),
            ],
            $this->lex("; Mein Kommentar\n; Mein andere Kommentar"),
        );
    }

    public function test_read_comment_macro(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT_MACRO, '#_', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 2)),
                new Token(Token::T_ATOM, 'a', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 3)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 3), new SourceLocation('string', 1, 3)),
            ],
            $this->lex('#_a'),
        );
    }

    public function test_read_multiline_comment(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, '#|test|#', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 8)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 8), new SourceLocation('string', 1, 8)),
            ],
            $this->lex('#|test|#'),
        );
    }

    public function test_read_nested_multiline_comment(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, '#|a #|b|# c|#', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 13)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 13), new SourceLocation('string', 1, 13)),
            ],
            $this->lex('#|a #|b|# c|#'),
        );
    }

    public function test_read_multiline_comment_with_newlines(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_COMMENT, "#|a\nb|#", new SourceLocation('string', 1, 0), new SourceLocation('string', 2, 3)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 2, 3), new SourceLocation('string', 2, 3)),
            ],
            $this->lex("#|a\nb|#"),
        );
    }

    public function test_read_single_syntax_char(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_OPEN_PARENTHESIS, '(', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 1)),
            ],
            $this->lex('('),
        );
    }

    public function test_read_empty_list(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_OPEN_PARENTHESIS, '(', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_CLOSE_PARENTHESIS, ')', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 2)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 2)),
            ],
            $this->lex('()'),
        );
    }

    public function test_read_set_literal(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_HASH_OPEN_BRACE, '#{', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 2)),
                new Token(Token::T_ATOM, '1', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 3)),
                new Token(Token::T_CLOSE_BRACE, '}', new SourceLocation('string', 1, 3), new SourceLocation('string', 1, 4)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 4), new SourceLocation('string', 1, 4)),
            ],
            $this->lex('#{1}'),
        );
    }

    public function test_read_word(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_ATOM, 'true', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 4)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 4), new SourceLocation('string', 1, 4)),
            ],
            $this->lex('true'),
        );
    }

    public function test_read_number(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_ATOM, '1', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 1)),
            ],
            $this->lex('1'),
        );
    }

    public function test_read_empty_string(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_STRING, '""', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 2)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 2)),
            ],
            $this->lex('""'),
        );
    }

    public function test_read_string(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_STRING, '"test"', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 6)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 6), new SourceLocation('string', 1, 6)),
            ],
            $this->lex('"test"'),
        );
    }

    public function test_read_escaped_string(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_STRING, '"te\\"st"', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 8)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 8), new SourceLocation('string', 1, 8)),
            ],
            $this->lex('"te\\"st"'),
        );
    }

    public function test_read_vector(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_OPEN_BRACKET, '[', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_ATOM, 'true', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 5)),
                new Token(Token::T_WHITESPACE, ' ', new SourceLocation('string', 1, 5), new SourceLocation('string', 1, 6)),
                new Token(Token::T_ATOM, 'false', new SourceLocation('string', 1, 6), new SourceLocation('string', 1, 11)),
                new Token(Token::T_CLOSE_BRACKET, ']', new SourceLocation('string', 1, 11), new SourceLocation('string', 1, 12)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 12), new SourceLocation('string', 1, 12)),
            ],
            $this->lex('[true false]'),
        );
    }

    public function test_read_hash_fn(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_HASH_FN, '#(', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 2)),
                new Token(Token::T_ATOM, 'add', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 5)),
                new Token(Token::T_CLOSE_PARENTHESIS, ')', new SourceLocation('string', 1, 5), new SourceLocation('string', 1, 6)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 6), new SourceLocation('string', 1, 6)),
            ],
            $this->lex('#(add)'),
        );
    }

    public function test_read_hash_fn_with_percent_placeholder(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_HASH_FN, '#(', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 2)),
                new Token(Token::T_ATOM, 'add', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 5)),
                new Token(Token::T_WHITESPACE, ' ', new SourceLocation('string', 1, 5), new SourceLocation('string', 1, 6)),
                new Token(Token::T_ATOM, '%', new SourceLocation('string', 1, 6), new SourceLocation('string', 1, 7)),
                new Token(Token::T_CLOSE_PARENTHESIS, ')', new SourceLocation('string', 1, 7), new SourceLocation('string', 1, 8)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 8), new SourceLocation('string', 1, 8)),
            ],
            $this->lex('#(add %)'),
        );
    }

    public function test_hash_comment_emits_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('# test comment');
        } finally {
            restore_error_handler();
        }

        self::assertNotNull($warning);
        self::assertStringContainsString('v0.33', $warning);
        self::assertStringContainsString('";"', $warning);
    }

    public function test_semicolon_comment_does_not_emit_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('; test comment');
        } finally {
            restore_error_handler();
        }

        self::assertNull($warning);
    }

    public function test_multiline_comment_emits_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('#|test|#');
        } finally {
            restore_error_handler();
        }

        self::assertNotNull($warning);
        self::assertStringContainsString('v0.33', $warning);
        self::assertStringContainsString(';;', $warning);
    }

    public function test_pipe_fn_emits_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('|(add $)');
        } finally {
            restore_error_handler();
        }

        self::assertNotNull($warning);
        self::assertStringContainsString('#()', $warning);
    }

    public function test_hash_fn_does_not_emit_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('#(add %)');
        } finally {
            restore_error_handler();
        }

        self::assertNull($warning);
    }

    public function test_comma_unquote_emits_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('`(foo ,bar)');
        } finally {
            restore_error_handler();
        }

        self::assertNotNull($warning);
        self::assertStringContainsString('"~"', $warning);
    }

    public function test_comma_splicing_emits_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('`(foo ,@bar)');
        } finally {
            restore_error_handler();
        }

        self::assertNotNull($warning);
        self::assertStringContainsString('"~@"', $warning);
    }

    public function test_tilde_unquote_does_not_emit_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('`(foo ~bar)');
        } finally {
            restore_error_handler();
        }

        self::assertNull($warning);
    }

    public function test_tilde_splicing_does_not_emit_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->lex('`(foo ~@bar)');
        } finally {
            restore_error_handler();
        }

        self::assertNull($warning);
    }

    public function test_regex_literal(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_REGEX, '#"\d+"', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 6)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 6), new SourceLocation('string', 1, 6)),
            ],
            $this->lex('#"\d+"'),
        );
    }

    public function test_deref_token(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_DEREF, '@', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_ATOM, 'a', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 2)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 2)),
            ],
            $this->lex('@a'),
        );
    }

    public function test_tilde_unquote_token(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_UNQUOTE, '~', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 1)),
                new Token(Token::T_ATOM, 'a', new SourceLocation('string', 1, 1), new SourceLocation('string', 1, 2)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 2)),
            ],
            $this->lex('~a'),
        );
    }

    public function test_tilde_unquote_splicing_token(): void
    {
        self::assertEquals(
            [
                new Token(Token::T_UNQUOTE_SPLICING, '~@', new SourceLocation('string', 1, 0), new SourceLocation('string', 1, 2)),
                new Token(Token::T_ATOM, 'a', new SourceLocation('string', 1, 2), new SourceLocation('string', 1, 3)),
                new Token(Token::T_EOF, '', new SourceLocation('string', 1, 3), new SourceLocation('string', 1, 3)),
            ],
            $this->lex('~@a'),
        );
    }

    public function test_php_bitnot_symbol_is_single_atom(): void
    {
        $tokens = $this->lex('php/~');

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame('php/~', $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_reader_conditional_token(): void
    {
        $tokens = $this->lex('#?(:phel 42 :default 0)');
        self::assertSame(Token::T_READER_COND, $tokens[0]->getType());
        self::assertSame('#?(', $tokens[0]->getCode());
    }

    public function test_reader_conditional_splicing_token(): void
    {
        $tokens = $this->lex('#?@(:phel [1 2])');
        self::assertSame(Token::T_READER_COND_SPLICING, $tokens[0]->getType());
        self::assertSame('#?@(', $tokens[0]->getCode());
    }

    public function test_atom_with_trailing_hash_is_single_token(): void
    {
        $tokens = $this->lex('foo#');
        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame('foo#', $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_atom_with_trailing_hash_followed_by_reader_conditional(): void
    {
        $tokens = $this->lex('report-success# #?(:phel 1 :default 2)');

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame('report-success#', $tokens[0]->getCode());
        self::assertSame(Token::T_WHITESPACE, $tokens[1]->getType());
        self::assertSame(Token::T_READER_COND, $tokens[2]->getType());
        self::assertSame('#?(', $tokens[2]->getCode());
    }

    public function test_atom_with_trailing_quote_is_single_token(): void
    {
        $tokens = $this->lex("a'");

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame("a'", $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_atom_with_multiple_trailing_quotes(): void
    {
        $tokens = $this->lex("a''");

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame("a''", $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_atom_with_interior_quote(): void
    {
        $tokens = $this->lex("a'b");

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame("a'b", $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_leading_quote_still_quotes_symbol(): void
    {
        $tokens = $this->lex("'foo");

        self::assertSame(Token::T_QUOTE, $tokens[0]->getType());
        self::assertSame("'", $tokens[0]->getCode());
        self::assertSame(Token::T_ATOM, $tokens[1]->getType());
        self::assertSame('foo', $tokens[1]->getCode());
        self::assertSame(Token::T_EOF, $tokens[2]->getType());
    }

    public function test_leading_quote_on_list(): void
    {
        $tokens = $this->lex("'(1 2)");

        self::assertSame(Token::T_QUOTE, $tokens[0]->getType());
        self::assertSame("'", $tokens[0]->getCode());
        self::assertSame(Token::T_OPEN_PARENTHESIS, $tokens[1]->getType());
        self::assertSame(Token::T_ATOM, $tokens[2]->getType());
        self::assertSame('1', $tokens[2]->getCode());
        self::assertSame(Token::T_WHITESPACE, $tokens[3]->getType());
        self::assertSame(Token::T_ATOM, $tokens[4]->getType());
        self::assertSame('2', $tokens[4]->getCode());
        self::assertSame(Token::T_CLOSE_PARENTHESIS, $tokens[5]->getType());
        self::assertSame(Token::T_EOF, $tokens[6]->getType());
    }

    public function test_trailing_quote_in_def_list(): void
    {
        $tokens = $this->lex("(def a' 123)");

        self::assertSame(Token::T_OPEN_PARENTHESIS, $tokens[0]->getType());
        self::assertSame(Token::T_ATOM, $tokens[1]->getType());
        self::assertSame('def', $tokens[1]->getCode());
        self::assertSame(Token::T_WHITESPACE, $tokens[2]->getType());
        self::assertSame(Token::T_ATOM, $tokens[3]->getType());
        self::assertSame("a'", $tokens[3]->getCode());
        self::assertSame(Token::T_WHITESPACE, $tokens[4]->getType());
        self::assertSame(Token::T_ATOM, $tokens[5]->getType());
        self::assertSame('123', $tokens[5]->getCode());
        self::assertSame(Token::T_CLOSE_PARENTHESIS, $tokens[6]->getType());
        self::assertSame(Token::T_EOF, $tokens[7]->getType());
    }

    public function test_symbol_with_trailing_quote_and_hash(): void
    {
        // `a'#` — trailing hash still captured via the atom's `\#?` suffix.
        $tokens = $this->lex("a'#");

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame("a'#", $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_double_hash_inf_lexes_as_single_token(): void
    {
        $tokens = $this->lex('##Inf');

        self::assertSame(Token::T_SYMBOLIC_NUMBER, $tokens[0]->getType());
        self::assertSame('##Inf', $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_double_hash_negative_inf_lexes_as_single_token(): void
    {
        $tokens = $this->lex('##-Inf');

        self::assertSame(Token::T_SYMBOLIC_NUMBER, $tokens[0]->getType());
        self::assertSame('##-Inf', $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_double_hash_nan_lexes_as_single_token(): void
    {
        $tokens = $this->lex('##NaN');

        self::assertSame(Token::T_SYMBOLIC_NUMBER, $tokens[0]->getType());
        self::assertSame('##NaN', $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_double_hash_with_space_does_not_lex_as_symbolic_number(): void
    {
        // `## Inf` with a space after `##` must NOT match the symbolic number rule.
        // `##` on its own is not a valid lexer state.
        $this->expectException(LexerValueException::class);
        $this->lex('## Inf');
    }

    public function test_hash_tag_lexes_as_single_token(): void
    {
        $tokens = $this->lex('#cpp');

        self::assertSame(Token::T_TAGGED_LITERAL, $tokens[0]->getType());
        self::assertSame('#cpp', $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_hash_tag_does_not_consume_following_form(): void
    {
        // `#cpp (1 2 3)` → tag token, whitespace, ( , atoms..., )
        $tokens = $this->lex('#cpp (1 2 3)');

        self::assertSame(Token::T_TAGGED_LITERAL, $tokens[0]->getType());
        self::assertSame('#cpp', $tokens[0]->getCode());
        self::assertSame(Token::T_WHITESPACE, $tokens[1]->getType());
        self::assertSame(Token::T_OPEN_PARENTHESIS, $tokens[2]->getType());
        self::assertSame(Token::T_ATOM, $tokens[3]->getType());
        self::assertSame('1', $tokens[3]->getCode());
    }

    public function test_hash_tag_with_dashes_and_digits(): void
    {
        $tokens = $this->lex('#my-tag1');

        self::assertSame(Token::T_TAGGED_LITERAL, $tokens[0]->getType());
        self::assertSame('#my-tag1', $tokens[0]->getCode());
    }

    public function test_hash_underscore_still_form_skip(): void
    {
        // regression: #_ must still match T_COMMENT_MACRO, not T_TAGGED_LITERAL
        $tokens = $this->lex('#_a');

        self::assertSame(Token::T_COMMENT_MACRO, $tokens[0]->getType());
        self::assertSame('#_', $tokens[0]->getCode());
        self::assertSame(Token::T_ATOM, $tokens[1]->getType());
    }

    public function test_hash_brace_still_set_literal(): void
    {
        $tokens = $this->lex('#{1 2}');
        self::assertSame(Token::T_HASH_OPEN_BRACE, $tokens[0]->getType());
    }

    public function test_hash_paren_still_anon_fn(): void
    {
        $tokens = $this->lex('#(+ % 1)');
        self::assertSame(Token::T_HASH_FN, $tokens[0]->getType());
    }

    public function test_hash_question_still_reader_conditional(): void
    {
        $tokens = $this->lex('#?(:phel 1)');
        self::assertSame(Token::T_READER_COND, $tokens[0]->getType());
    }

    public function test_hash_quote_still_regex(): void
    {
        $tokens = $this->lex('#"foo"');
        self::assertSame(Token::T_REGEX, $tokens[0]->getType());
    }

    public function test_hash_pipe_multiline_comment_still_deprecated_but_works(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $tokens = $this->lex('#| multiline |#');
        } finally {
            restore_error_handler();
        }

        self::assertSame(Token::T_COMMENT, $tokens[0]->getType());
        self::assertSame('#| multiline |#', $tokens[0]->getCode());
        self::assertNotNull($warning);
        self::assertStringContainsString('v0.33', $warning);
    }

    public function test_bare_hash_line_comment_still_deprecated_but_works(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $tokens = $this->lex("# a bare comment\n");
        } finally {
            restore_error_handler();
        }

        self::assertSame(Token::T_COMMENT, $tokens[0]->getType());
        self::assertNotNull($warning);
        self::assertStringContainsString('v0.33', $warning);
    }

    public function test_var_quote_prefix_lexes_as_single_token(): void
    {
        // `#'bar` → T_VAR_QUOTE token followed by atom `bar`.
        $tokens = $this->lex("#'bar");

        self::assertSame(Token::T_VAR_QUOTE, $tokens[0]->getType());
        self::assertSame("#'", $tokens[0]->getCode());
        self::assertSame(Token::T_ATOM, $tokens[1]->getType());
        self::assertSame('bar', $tokens[1]->getCode());
        self::assertSame(Token::T_EOF, $tokens[2]->getType());
    }

    public function test_var_quote_prefix_does_not_match_comment_rule(): void
    {
        // Regression: before the fix, `#'bar)` was eaten by the comment rule
        // up to EOL, which broke `(var? #'bar)` with "Unterminated list (EOF)".
        $tokens = $this->lex("(var? #'bar)");

        self::assertSame(Token::T_OPEN_PARENTHESIS, $tokens[0]->getType());
        self::assertSame(Token::T_ATOM, $tokens[1]->getType());
        self::assertSame('var?', $tokens[1]->getCode());
        self::assertSame(Token::T_WHITESPACE, $tokens[2]->getType());
        self::assertSame(Token::T_VAR_QUOTE, $tokens[3]->getType());
        self::assertSame(Token::T_ATOM, $tokens[4]->getType());
        self::assertSame('bar', $tokens[4]->getCode());
        self::assertSame(Token::T_CLOSE_PARENTHESIS, $tokens[5]->getType());
    }

    public function test_char_literal_single_alpha(): void
    {
        $tokens = $this->lex('\\a');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\a', $tokens[0]->getCode());
        self::assertSame(Token::T_EOF, $tokens[1]->getType());
    }

    public function test_char_literal_single_digit(): void
    {
        $tokens = $this->lex('\\1');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\1', $tokens[0]->getCode());
    }

    public function test_char_literal_single_uppercase(): void
    {
        $tokens = $this->lex('\\Z');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\Z', $tokens[0]->getCode());
    }

    public function test_char_literal_named_space(): void
    {
        $tokens = $this->lex('\\space');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\space', $tokens[0]->getCode());
    }

    public function test_char_literal_named_newline(): void
    {
        $tokens = $this->lex('\\newline');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\newline', $tokens[0]->getCode());
    }

    public function test_char_literal_named_tab(): void
    {
        $tokens = $this->lex('\\tab');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\tab', $tokens[0]->getCode());
    }

    public function test_char_literal_named_formfeed(): void
    {
        $tokens = $this->lex('\\formfeed');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\formfeed', $tokens[0]->getCode());
    }

    public function test_char_literal_named_backspace(): void
    {
        $tokens = $this->lex('\\backspace');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\backspace', $tokens[0]->getCode());
    }

    public function test_char_literal_named_return(): void
    {
        $tokens = $this->lex('\\return');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\return', $tokens[0]->getCode());
    }

    public function test_char_literal_unicode_lowercase_hex(): void
    {
        $tokens = $this->lex('\\u03a9');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\u03a9', $tokens[0]->getCode());
    }

    public function test_char_literal_unicode_uppercase_hex(): void
    {
        $tokens = $this->lex('\\u03A9');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\u03A9', $tokens[0]->getCode());
    }

    public function test_char_literal_octal(): void
    {
        $tokens = $this->lex('\\o123');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\o123', $tokens[0]->getCode());
    }

    public function test_char_literal_paren(): void
    {
        $tokens = $this->lex('\\(');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\(', $tokens[0]->getCode());
    }

    public function test_char_literal_bracket(): void
    {
        $tokens = $this->lex('\\[');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\[', $tokens[0]->getCode());
    }

    public function test_char_literal_brace(): void
    {
        $tokens = $this->lex('\\{');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\{', $tokens[0]->getCode());
    }

    public function test_char_literal_backslash(): void
    {
        // Source `\\` — a backslash followed by a backslash, i.e. the char literal for `\`.
        $tokens = $this->lex('\\\\');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\\\', $tokens[0]->getCode());
    }

    public function test_char_literal_double_quote(): void
    {
        $tokens = $this->lex('\\"');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\"', $tokens[0]->getCode());
    }

    public function test_char_literal_comma(): void
    {
        $tokens = $this->lex('\\,');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\,', $tokens[0]->getCode());
    }

    public function test_char_literal_semicolon(): void
    {
        $tokens = $this->lex('\\;');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\;', $tokens[0]->getCode());
    }

    public function test_char_literal_followed_by_space(): void
    {
        $tokens = $this->lex('\\a ');

        self::assertSame(Token::T_CHAR, $tokens[0]->getType());
        self::assertSame('\\a', $tokens[0]->getCode());
        self::assertSame(Token::T_WHITESPACE, $tokens[1]->getType());
    }

    public function test_char_literal_inside_list(): void
    {
        $tokens = $this->lex('(\\a)');

        self::assertSame(Token::T_OPEN_PARENTHESIS, $tokens[0]->getType());
        self::assertSame(Token::T_CHAR, $tokens[1]->getType());
        self::assertSame('\\a', $tokens[1]->getCode());
        self::assertSame(Token::T_CLOSE_PARENTHESIS, $tokens[2]->getType());
    }

    public function test_char_literal_inside_vector(): void
    {
        $tokens = $this->lex('[\\a \\b]');

        self::assertSame(Token::T_OPEN_BRACKET, $tokens[0]->getType());
        self::assertSame(Token::T_CHAR, $tokens[1]->getType());
        self::assertSame('\\a', $tokens[1]->getCode());
        self::assertSame(Token::T_WHITESPACE, $tokens[2]->getType());
        self::assertSame(Token::T_CHAR, $tokens[3]->getType());
        self::assertSame('\\b', $tokens[3]->getCode());
        self::assertSame(Token::T_CLOSE_BRACKET, $tokens[4]->getType());
    }

    public function test_fqn_two_segments_still_atom(): void
    {
        // Regression: `\Phel\Lang` is an FQN atom, not two char literals.
        $tokens = $this->lex('\\Phel\\Lang');

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame('\\Phel\\Lang', $tokens[0]->getCode());
    }

    public function test_fqn_three_segments_still_atom(): void
    {
        // Regression: `\Phel\Lang\Symbol` is an FQN atom used in PHP interop.
        $tokens = $this->lex(Symbol::class);

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame(Symbol::class, $tokens[0]->getCode());
    }

    public function test_fqn_lowercase_still_atom(): void
    {
        // Regression: `\foo\bar` stays an FQN atom — not a char + atom pair.
        $tokens = $this->lex('\\foo\\bar');

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame('\\foo\\bar', $tokens[0]->getCode());
    }

    public function test_backslash_followed_by_identifier_chars_still_atom(): void
    {
        // Regression: `\aFoo` is a single atom, not char `a` + atom `Foo`, because
        // the char-literal lookahead rejects identifier continuation after the char.
        $tokens = $this->lex('\\aFoo');

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame('\\aFoo', $tokens[0]->getCode());
    }

    public function test_backslash_space_identifier_still_atom(): void
    {
        // `\spaces` is NOT `\space` + `s` — the lookahead rejects the `\space`
        // named form if followed by another identifier char, so the whole thing
        // falls through to the atom rule.
        $tokens = $this->lex('\\spaces');

        self::assertSame(Token::T_ATOM, $tokens[0]->getType());
        self::assertSame('\\spaces', $tokens[0]->getCode());
    }

    private function lex(string $string): array
    {
        $lexer = $this->compilerFactory->createLexer();

        return iterator_to_array($lexer->lexString($string, 'string'));
    }
}
