<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpFormTokenizer;
use PHPUnit\Framework\TestCase;

final class PhpFormTokenizerTest extends TestCase
{
    private PhpFormTokenizer $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new PhpFormTokenizer();
    }

    public function test_splits_on_whitespace_and_commas(): void
    {
        [$tokens, $endsOpen] = $this->tokenizer->topLevel('a b, c ');

        self::assertSame(['a', 'b', 'c'], $tokens);
        self::assertFalse($endsOpen, 'trailing whitespace means the last token is complete');
    }

    public function test_keeps_nested_forms_whole(): void
    {
        [$tokens] = $this->tokenizer->topLevel('dt (modify "x y") get');

        self::assertSame(['dt', '(modify "x y")', 'get'], $tokens);
    }

    public function test_reports_trailing_token_as_open(): void
    {
        [$tokens, $endsOpen] = $this->tokenizer->topLevel('a b');

        self::assertSame(['a', 'b'], $tokens);
        self::assertTrue($endsOpen);
    }

    public function test_string_literal_hides_delimiters(): void
    {
        [$tokens] = $this->tokenizer->topLevel('"a b (c)" d');

        self::assertSame(['"a b (c)"', 'd'], $tokens);
    }

    public function test_line_comment_is_skipped(): void
    {
        [$tokens] = $this->tokenizer->topLevel("a ; comment b\nc");

        self::assertSame(['a', 'c'], $tokens);
    }

    public function test_empty_content_yields_no_tokens(): void
    {
        [$tokens, $endsOpen] = $this->tokenizer->topLevel('   ');

        self::assertSame([], $tokens);
        self::assertFalse($endsOpen);
    }
}
