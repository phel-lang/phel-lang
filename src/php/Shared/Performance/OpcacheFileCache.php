<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function is_file;
use function scandir;
use function sort;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Reads the on-disk layout of an OPcache file cache directory.
 *
 * OPcache stores one entry per compiled file at
 * `<file_cache>/<system id><absolute source path>.bin`, so the 32-character
 * system id opens the path: a plain `/opt/app/x.php` yields the top-level
 * entry `<id>`, while a `phar:///opt/app/x.phar/y.php` source yields
 * `<id>phar:`. The system id changes with the PHP build and with ini settings,
 * so every subtree but the current one is dead weight no process can read
 * again (#3268).
 *
 * Pure reads; the deletions live in `OpcacheFileCachePruner`.
 */
final readonly class OpcacheFileCache
{
    public const string BIN_SUFFIX = '.bin';

    private const int SYSTEM_ID_LENGTH = 32;

    public function __construct(
        private string $directory,
    ) {}

    public function path(): string
    {
        return $this->directory;
    }

    public function exists(): bool
    {
        return is_dir($this->directory);
    }

    /**
     * Top-level entry names, sorted, without the dot entries.
     *
     * @return list<string>
     */
    public function entries(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $entries = [];
        foreach (scandir($this->directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $entries[] = $entry;
            }
        }

        sort($entries);

        return $entries;
    }

    /**
     * The system id the running process writes under, read off the `.bin` of a
     * file it has already compiled, or `''` when no subtree holds one.
     *
     * Probing beats deriving: the id mixes the PHP version, the Zend extension
     * build id and opcache directives, none of which userland can reproduce.
     */
    public function detectSystemId(string $compiledFile): string
    {
        foreach ($this->entries() as $entry) {
            $candidate = substr($entry, 0, self::SYSTEM_ID_LENGTH);
            if (strlen($candidate) !== self::SYSTEM_ID_LENGTH) {
                continue;
            }

            if (is_file($this->binPath($candidate, $compiledFile))) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Absolute paths of the top-level entries that belong to another system id.
     * An empty `$systemId` matches every entry, so nothing reads as foreign:
     * a caller that could not detect the current id prunes nothing.
     *
     * @return list<string>
     */
    public function foreignEntries(string $systemId): array
    {
        $foreign = [];
        foreach ($this->entries() as $entry) {
            if (!str_starts_with($entry, $systemId)) {
                $foreign[] = $this->directory . '/' . $entry;
            }
        }

        return $foreign;
    }

    public function subtreePath(string $systemId, string $sourcePath): string
    {
        return $this->directory . '/' . $systemId . $sourcePath;
    }

    public function binPath(string $systemId, string $sourcePath): string
    {
        return $this->subtreePath($systemId, $sourcePath) . self::BIN_SUFFIX;
    }

    /**
     * Source path a `.bin` below `<directory>/<systemId>` was compiled from,
     * or `''` when the path does not sit in that subtree.
     */
    public function sourcePathOfBin(string $systemId, string $binPath): string
    {
        $prefix = $this->directory . '/' . $systemId;
        if (!str_starts_with($binPath, $prefix)) {
            return '';
        }

        return substr($binPath, strlen($prefix), -strlen(self::BIN_SUFFIX));
    }

    public function sizeInBytes(): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }

        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $bytes += $file->getSize();
            }
        }

        return $bytes;
    }
}
