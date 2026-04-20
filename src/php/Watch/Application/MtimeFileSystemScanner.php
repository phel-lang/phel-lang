<?php

declare(strict_types=1);

namespace Phel\Watch\Application;

use Phel\Watch\Domain\FileSystemScannerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function clearstatcache;
use function filemtime;
use function filesize;
use function is_dir;
use function is_file;

/**
 * Filesystem scanner that walks directories recursively and returns a
 * snapshot of `.phel` files keyed by realpath with mtime + size.
 */
final class MtimeFileSystemScanner implements FileSystemScannerInterface
{
    private const string PHEL_FILE_REGEX = '/^.+\.(phel|cljc)$/i';

    /**
     * @param list<string> $paths
     *
     * @return array<string, array{mtime:int, size:int}>
     */
    public function snapshot(array $paths): array
    {
        clearstatcache();
        $snapshot = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $this->statInto($snapshot, $path);
                continue;
            }

            if (is_dir($path)) {
                $this->walkDirInto($snapshot, $path);
            }
        }

        return $snapshot;
    }

    /**
     * @param array<string, array{mtime:int, size:int}> $snapshot
     */
    private function walkDirInto(array &$snapshot, string $dir): void
    {
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            $filtered = new RegexIterator($iterator, self::PHEL_FILE_REGEX, RegexIterator::GET_MATCH);

            foreach ($filtered as $match) {
                if (!isset($match[0])) {
                    continue;
                }

                $this->statInto($snapshot, (string) $match[0]);
            }
        } catch (UnexpectedValueException) {
            // Unreadable directories are silently skipped — same convention as
            // NamespaceExtractor.
        }
    }

    /**
     * @param array<string, array{mtime:int, size:int}> $snapshot
     */
    private function statInto(array &$snapshot, string $file): void
    {
        $mtime = @filemtime($file);
        $size = @filesize($file);
        if ($mtime === false || $size === false) {
            return;
        }

        $snapshot[$file] = ['mtime' => $mtime, 'size' => $size];
    }
}
