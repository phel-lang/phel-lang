<?php

declare(strict_types=1);

namespace Phel\Shared\SourceMap;

use function fclose;
use function fgets;
use function file_get_contents;
use function fopen;
use function get_included_files;
use function in_array;
use function realpath;

/**
 * Inline source-map headers of compiled files this process `require`d before
 * the file on disk was overwritten with different code. PHP keeps running the
 * code it loaded, and its frames still name the same path, so an error report
 * must map them through the header that code was compiled with, not the one
 * on disk now. Lives in memory only: the process that loaded the code is the
 * only one that can raise a frame from it.
 */
final class SupersededSourceMaps
{
    /**
     * Inline metadata sits within the first lines of a compiled file:
     * `<?php`, an optional `declare(ticks=1);`, then the filename and the
     * mappings comments.
     */
    public const int HEADER_LINES = 4;

    /** @var array<string, list<string>> */
    private static array $headers = [];

    private function __construct() {}

    /**
     * Keeps the header of `$compiledPath` when this process loaded it and
     * `$newContent` is about to replace it. The first loaded version wins:
     * a later overwrite never replaces the header of the code PHP runs.
     */
    public static function keepBeforeOverwrite(string $compiledPath, string $newContent): void
    {
        $path = realpath($compiledPath);
        if ($path === false || isset(self::$headers[$path])) {
            return;
        }

        if (!in_array($path, get_included_files(), true)) {
            return;
        }

        if (file_get_contents($path) === $newContent) {
            return;
        }

        $header = self::readHeader($path);
        if ($header !== []) {
            self::$headers[$path] = $header;
        }
    }

    /**
     * @return list<string>|null the header lines the loaded code was compiled with
     */
    public static function headerOf(string $compiledPath): ?array
    {
        $path = realpath($compiledPath);

        return $path === false ? null : self::$headers[$path] ?? null;
    }

    public static function has(string $compiledPath): bool
    {
        return self::headerOf($compiledPath) !== null;
    }

    public static function reset(): void
    {
        self::$headers = [];
    }

    /**
     * @return list<string>
     */
    private static function readHeader(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $lines = [];
        try {
            for ($i = 0; $i < self::HEADER_LINES; ++$i) {
                $line = fgets($handle);
                if ($line === false) {
                    break;
                }

                $lines[] = $line;
            }
        } finally {
            fclose($handle);
        }

        return $lines;
    }
}
