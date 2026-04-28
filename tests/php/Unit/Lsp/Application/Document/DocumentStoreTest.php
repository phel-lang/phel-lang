<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Document;

use Phel\Lsp\Application\Document\DocumentStore;
use PHPUnit\Framework\TestCase;

final class DocumentStoreTest extends TestCase
{
    public function test_open_stores_document_and_returns_it(): void
    {
        $store = new DocumentStore();

        $document = $store->open('file:///x.phel', 'phel', 1, '(ns x)');

        self::assertSame('file:///x.phel', $document->uri);
        self::assertSame('(ns x)', $document->text);
        self::assertSame(1, $document->version);
        self::assertSame($document, $store->get('file:///x.phel'));
    }

    public function test_get_returns_null_for_missing_uri(): void
    {
        $store = new DocumentStore();

        self::assertNull($store->get('file:///gone.phel'));
    }

    public function test_replace_updates_text_and_version_when_open(): void
    {
        $store = new DocumentStore();
        $store->open('file:///x.phel', 'phel', 1, 'old');

        $replaced = $store->replace('file:///x.phel', 2, 'new');

        self::assertNotNull($replaced);
        self::assertSame('new', $replaced->text);
        self::assertSame(2, $replaced->version);
    }

    public function test_replace_returns_null_when_document_not_open(): void
    {
        $store = new DocumentStore();

        self::assertNull($store->replace('file:///nope.phel', 1, 'x'));
    }

    public function test_close_removes_document(): void
    {
        $store = new DocumentStore();
        $store->open('file:///x.phel', 'phel', 1, 'data');

        $store->close('file:///x.phel');

        self::assertNull($store->get('file:///x.phel'));
    }

    public function test_close_on_missing_uri_is_a_noop(): void
    {
        $store = new DocumentStore();

        $store->close('file:///gone.phel');
        self::assertSame([], $store->uris());
    }

    public function test_uris_returns_all_currently_open(): void
    {
        $store = new DocumentStore();
        $store->open('file:///a.phel', 'phel', 1, '');
        $store->open('file:///b.phel', 'phel', 1, '');

        $uris = $store->uris();

        self::assertContains('file:///a.phel', $uris);
        self::assertContains('file:///b.phel', $uris);
        self::assertCount(2, $uris);
    }

    public function test_open_replaces_existing_document(): void
    {
        $store = new DocumentStore();
        $store->open('file:///x.phel', 'phel', 1, 'first');

        $store->open('file:///x.phel', 'phel', 2, 'second');

        $document = $store->get('file:///x.phel');
        self::assertNotNull($document);
        self::assertSame('second', $document->text);
        self::assertSame(2, $document->version);
    }
}
