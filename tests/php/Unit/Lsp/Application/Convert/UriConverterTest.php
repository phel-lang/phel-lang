<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Convert;

use Phel\Lsp\Application\Convert\UriConverter;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function windows_file_uris(): iterable
    {
        yield 'drive' => ['file:///C:/Users/x/a.phel', 'C:/Users/x/a.phel'];
        yield 'encoded space' => ['file:///C:/Users/x/My%20Project/a.phel', 'C:/Users/x/My Project/a.phel'];
        yield 'lowercase drive' => ['file:///d:/a.phel', 'd:/a.phel'];
    }

    #[DataProvider('windows_file_uris')]
    public function test_windows_drive_uri_round_trips(string $uri, string $path): void
    {
        $converter = new UriConverter();

        self::assertSame($path, $converter->toFilePath($uri));
        self::assertSame($uri, $converter->fromFilePath($path));
    }

    public function test_encoded_drive_marker_is_decoded_before_normalization(): void
    {
        $converter = new UriConverter();
        foreach (['file:///C%3A/Users/x/a.phel', 'file:///%43:/Users/x/a.phel'] as $uri) {
            $path = $converter->toFilePath($uri);
            self::assertSame('C:/Users/x/a.phel', $path);
            self::assertSame('file:///C:/Users/x/a.phel', $converter->fromFilePath($path));
        }
    }

    public function test_localhost_windows_drive_is_a_local_path(): void
    {
        self::assertSame('C:/a.phel', new UriConverter()->toFilePath('file://LOCALHOST/C:/a.phel'));
    }

    public function test_uppercase_file_scheme_uses_the_same_conversion(): void
    {
        self::assertSame('C:/a.phel', new UriConverter()->toFilePath('FILE:///C:/a.phel'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unchanged_uri_paths(): iterable
    {
        yield 'posix' => ['file:///tmp/a.phel', '/tmp/a.phel'];
        yield 'posix colon segment' => ['file:///tmp/C:/a.phel', '/tmp/C:/a.phel'];
        yield 'drive-relative spelling' => ['file:///C:a.phel', 'C:a.phel'];
        yield 'encoded characters' => ['file:///tmp/a%20b%23c%3Fd.phel', '/tmp/a b#c?d.phel'];
        yield 'encoded control byte' => ['file:///tmp/a%09b.phel', "/tmp/a\tb.phel"];
        yield 'remote host' => ['file://server/share/a.phel', '/share/a.phel'];
        yield 'remote drive path' => ['file://server/C:/a.phel', '/C:/a.phel'];
        yield 'unc path spelling' => ['file:////server/share/a.phel', '//server/share/a.phel'];
    }

    #[DataProvider('unchanged_uri_paths')]
    public function test_non_local_drive_paths_keep_their_existing_decoding(string $uri, string $path): void
    {
        self::assertSame($path, new UriConverter()->toFilePath($uri));
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
