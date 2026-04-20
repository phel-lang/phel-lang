<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Document;

use Phel\Lsp\Application\Document\Document;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    public function test_constructor_captures_initial_fields(): void
    {
        $document = new Document('file:///x.phel', 'phel', 3, '(ns x)');

        self::assertSame('file:///x.phel', $document->uri);
        self::assertSame('phel', $document->languageId);
        self::assertSame(3, $document->version);
        self::assertSame('(ns x)', $document->text);
    }

    public function test_update_replaces_text_and_version(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'old');

        $document->update('new', 2);

        self::assertSame('new', $document->text);
        self::assertSame(2, $document->version);
    }

    public function test_bump_version_keeps_text_unchanged(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'same');

        $document->bumpVersion(5);

        self::assertSame('same', $document->text);
        self::assertSame(5, $document->version);
    }

    public function test_line_count_handles_lf_newlines(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, "a\nb\nc");

        self::assertSame(3, $document->lineCount());
    }

    public function test_line_count_handles_crlf_newlines(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, "a\r\nb\r\nc");

        self::assertSame(3, $document->lineCount());
    }

    public function test_apply_range_replaces_selection(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'hello world');

        $document->applyRange(
            ['start' => ['line' => 0, 'character' => 6], 'end' => ['line' => 0, 'character' => 11]],
            'there',
        );

        self::assertSame('hello there', $document->text);
    }

    public function test_apply_range_can_insert_text(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'ab');

        $document->applyRange(
            ['start' => ['line' => 0, 'character' => 1], 'end' => ['line' => 0, 'character' => 1]],
            'X',
        );

        self::assertSame('aXb', $document->text);
    }

    public function test_apply_range_spanning_multiple_lines(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, "one\ntwo\nthree");

        $document->applyRange(
            ['start' => ['line' => 0, 'character' => 1], 'end' => ['line' => 2, 'character' => 2]],
            'X',
        );

        self::assertSame('oXree', $document->text);
    }

    public function test_position_to_offset_past_eof_returns_text_length(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'abc');

        self::assertSame(3, $document->positionToOffset(['line' => 99, 'character' => 0]));
    }

    public function test_position_to_offset_negative_clamps_to_zero(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'abc');

        self::assertSame(0, $document->positionToOffset(['line' => -5, 'character' => -5]));
    }

    public function test_position_to_offset_clamps_character_to_line_length(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, "abc\ndef");

        // 'abc' is 3 chars long; position character 99 should clamp to 3.
        self::assertSame(3, $document->positionToOffset(['line' => 0, 'character' => 99]));
    }

    public function test_word_at_inside_identifier(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, '(ns my-app core)');

        self::assertSame('my-app', $document->wordAt(['line' => 0, 'character' => 4]));
    }

    public function test_word_at_treats_backslash_as_word_boundary(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'my-app\\core');

        // The identifier regex stops at backslash, so the cursor near the
        // start picks up only the first segment.
        self::assertSame('my-app', $document->wordAt(['line' => 0, 'character' => 3]));
    }

    public function test_word_at_whitespace_returns_empty(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'foo   bar');

        self::assertSame('', $document->wordAt(['line' => 0, 'character' => 4]));
    }

    public function test_word_at_out_of_bounds_line_returns_empty(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'foo');

        self::assertSame('', $document->wordAt(['line' => 10, 'character' => 0]));
    }

    public function test_word_at_edge_of_identifier(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'foo bar');

        self::assertSame('foo', $document->wordAt(['line' => 0, 'character' => 0]));
        self::assertSame('foo', $document->wordAt(['line' => 0, 'character' => 3]));
        self::assertSame('bar', $document->wordAt(['line' => 0, 'character' => 5]));
    }

    public function test_word_at_accepts_special_identifier_chars(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'empty? even? plus+ map*');

        self::assertSame('empty?', $document->wordAt(['line' => 0, 'character' => 3]));
        self::assertSame('even?', $document->wordAt(['line' => 0, 'character' => 9]));
        self::assertSame('plus+', $document->wordAt(['line' => 0, 'character' => 15]));
        self::assertSame('map*', $document->wordAt(['line' => 0, 'character' => 20]));
    }

    public function test_word_at_on_second_line(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, "line1\n(foo bar)");

        self::assertSame('foo', $document->wordAt(['line' => 1, 'character' => 1]));
    }

    public function test_one_based_line_col_converts_from_zero_based(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'whatever');

        self::assertSame([1, 1], $document->oneBasedLineCol(['line' => 0, 'character' => 0]));
        self::assertSame([6, 11], $document->oneBasedLineCol(['line' => 5, 'character' => 10]));
    }

    public function test_one_based_line_col_clamps_negative_to_one(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'whatever');

        self::assertSame([1, 1], $document->oneBasedLineCol(['line' => -99, 'character' => -99]));
    }
}
