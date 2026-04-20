<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Convert;

use Phel\Lsp\Application\Convert\UriConverter;
use PHPUnit\Framework\TestCase;

final class UriConverterTest extends TestCase
{
    public function test_to_file_path_strips_scheme(): void
    {
        $converter = new UriConverter();

        self::assertSame('/tmp/x.phel', $converter->toFilePath('file:///tmp/x.phel'));
    }

    public function test_to_file_path_decodes_percent_encoded(): void
    {
        $converter = new UriConverter();

        self::assertSame('/tmp/a b.phel', $converter->toFilePath('file:///tmp/a%20b.phel'));
    }

    public function test_to_file_path_returns_input_for_non_file_uri(): void
    {
        $converter = new UriConverter();

        self::assertSame('/tmp/x.phel', $converter->toFilePath('/tmp/x.phel'));
    }

    public function test_from_file_path_produces_file_uri(): void
    {
        $converter = new UriConverter();

        self::assertSame('file:///tmp/x.phel', $converter->fromFilePath('/tmp/x.phel'));
    }

    public function test_from_file_path_keeps_existing_file_uri(): void
    {
        $converter = new UriConverter();

        self::assertSame('file:///tmp/x.phel', $converter->fromFilePath('file:///tmp/x.phel'));
    }

    public function test_from_file_path_encodes_windows_drive(): void
    {
        $converter = new UriConverter();

        $uri = $converter->fromFilePath('C:\\Users\\me\\x.phel');

        self::assertStringStartsWith('file:///C:/', $uri);
    }

    public function test_is_file_uri_recognises_case_insensitively(): void
    {
        $converter = new UriConverter();

        self::assertTrue($converter->isFileUri('file:///x.phel'));
        self::assertTrue($converter->isFileUri('FILE:///x.phel'));
    }

    public function test_is_file_uri_false_for_bare_path(): void
    {
        $converter = new UriConverter();

        self::assertFalse($converter->isFileUri('/tmp/x.phel'));
        self::assertFalse($converter->isFileUri(''));
    }

    public function test_to_client_uri_keeps_file_uri_untouched(): void
    {
        $converter = new UriConverter();

        self::assertSame('file:///tmp/x.phel', $converter->toClientUri('file:///tmp/x.phel'));
    }

    public function test_to_client_uri_wraps_bare_path(): void
    {
        $converter = new UriConverter();

        self::assertSame('file:///tmp/x.phel', $converter->toClientUri('/tmp/x.phel'));
    }

    public function test_to_client_uri_returns_empty_on_empty(): void
    {
        $converter = new UriConverter();

        self::assertSame('', $converter->toClientUri(''));
    }
}
