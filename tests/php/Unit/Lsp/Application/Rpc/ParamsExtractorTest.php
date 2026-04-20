<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Rpc;

use Phel\Lsp\Application\Rpc\ParamsExtractor;
use PHPUnit\Framework\TestCase;

final class ParamsExtractorTest extends TestCase
{
    public function test_uri_returns_empty_string_when_text_document_missing(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame('', $extractor->uri([]));
    }

    public function test_uri_returns_empty_string_when_text_document_not_an_array(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame('', $extractor->uri(['textDocument' => 'nonsense']));
    }

    public function test_uri_returns_empty_string_when_uri_field_not_a_string(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame('', $extractor->uri(['textDocument' => ['uri' => 42]]));
    }

    public function test_uri_returns_the_uri_when_present_and_valid(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame('file:///tmp/x.phel', $extractor->uri([
            'textDocument' => ['uri' => 'file:///tmp/x.phel'],
        ]));
    }

    public function test_position_returns_null_when_position_missing(): void
    {
        $extractor = new ParamsExtractor();

        self::assertNull($extractor->position([]));
    }

    public function test_position_returns_null_when_line_is_not_an_int(): void
    {
        $extractor = new ParamsExtractor();

        self::assertNull($extractor->position(['position' => ['line' => '0', 'character' => 0]]));
    }

    public function test_position_returns_null_when_character_is_not_an_int(): void
    {
        $extractor = new ParamsExtractor();

        self::assertNull($extractor->position(['position' => ['line' => 0, 'character' => '0']]));
    }

    public function test_position_returns_the_tuple_on_valid_shape(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame(
            ['line' => 3, 'character' => 7],
            $extractor->position(['position' => ['line' => 3, 'character' => 7]]),
        );
    }

    public function test_version_returns_default_when_missing(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame(0, $extractor->version([]));
        self::assertSame(42, $extractor->version([], 42));
    }

    public function test_version_returns_value_when_present(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame(
            5,
            $extractor->version(['textDocument' => ['version' => 5]]),
        );
    }

    public function test_language_id_returns_default_when_missing(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame('phel', $extractor->languageId([]));
    }

    public function test_language_id_returns_value_when_present(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame(
            'clojure',
            $extractor->languageId(['textDocument' => ['languageId' => 'clojure']]),
        );
    }

    public function test_text_returns_default_when_missing(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame('', $extractor->text([]));
    }

    public function test_text_returns_value_when_present(): void
    {
        $extractor = new ParamsExtractor();

        self::assertSame(
            '(ns foo)',
            $extractor->text(['textDocument' => ['text' => '(ns foo)']]),
        );
    }

    public function test_is_valid_range_accepts_well_formed_range(): void
    {
        $extractor = new ParamsExtractor();

        self::assertTrue($extractor->isValidRange([
            'start' => ['line' => 0, 'character' => 0],
            'end' => ['line' => 0, 'character' => 1],
        ]));
    }

    public function test_is_valid_range_rejects_missing_end(): void
    {
        $extractor = new ParamsExtractor();

        self::assertFalse($extractor->isValidRange([
            'start' => ['line' => 0, 'character' => 0],
        ]));
    }

    public function test_is_valid_range_rejects_non_integer_offsets(): void
    {
        $extractor = new ParamsExtractor();

        self::assertFalse($extractor->isValidRange([
            'start' => ['line' => '0', 'character' => 0],
            'end' => ['line' => 0, 'character' => 1],
        ]));
    }
}
