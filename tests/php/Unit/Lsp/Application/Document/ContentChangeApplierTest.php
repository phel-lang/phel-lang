<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Document;

use Phel\Lsp\Application\Document\ContentChangeApplier;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use PHPUnit\Framework\TestCase;

final class ContentChangeApplierTest extends TestCase
{
    public function test_returns_false_when_changes_is_not_an_array(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'initial');

        $applied = $this->applier()->apply($document, 'not-an-array', 2);

        self::assertFalse($applied);
        self::assertSame('initial', $document->text);
        self::assertSame(1, $document->version);
    }

    public function test_empty_changes_array_still_bumps_version(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'initial');

        $applied = $this->applier()->apply($document, [], 2);

        self::assertTrue($applied);
        self::assertSame('initial', $document->text);
        self::assertSame(2, $document->version);
    }

    public function test_full_replacement_replaces_text(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'old');

        $this->applier()->apply($document, [['text' => 'new']], 3);

        self::assertSame('new', $document->text);
        self::assertSame(3, $document->version);
    }

    public function test_incremental_range_update_applies_to_slice(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'hello world');

        $this->applier()->apply($document, [[
            'text' => 'there',
            'range' => [
                'start' => ['line' => 0, 'character' => 6],
                'end' => ['line' => 0, 'character' => 11],
            ],
        ]], 4);

        self::assertSame('hello there', $document->text);
        self::assertSame(4, $document->version);
    }

    public function test_non_string_text_is_coerced_to_empty_string(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'abc');

        $this->applier()->apply($document, [['text' => 123]], 2);

        self::assertSame('', $document->text);
    }

    public function test_malformed_change_entry_is_skipped(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'start');

        $this->applier()->apply($document, ['not-array', ['text' => 'final']], 9);

        self::assertSame('final', $document->text);
        self::assertSame(9, $document->version);
    }

    public function test_invalid_range_falls_back_to_full_update(): void
    {
        $document = new Document('file:///x.phel', 'phel', 1, 'before');

        $this->applier()->apply($document, [[
            'text' => 'after',
            'range' => 'garbage',
        ]], 5);

        self::assertSame('after', $document->text);
        self::assertSame(5, $document->version);
    }

    private function applier(): ContentChangeApplier
    {
        return new ContentChangeApplier(new ParamsExtractor());
    }
}
