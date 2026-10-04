<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Gacela\Framework\Cache\FileCache;

use function dirname;

/**
 * Writes a `var_export`ed PHP payload to a cache file under an exclusive
 * `flock`, creating the parent directory on demand. The payload is computed
 * while the lock is held, so callers can safely merge the current on-disk
 * state into their in-memory entries before serializing.
 *
 * Readers `include` the cache without a lock, so the file is replaced by a
 * rename, never rewritten in place. The lock lives on a sibling file because
 * the rename gives the cache file a new inode.
 *
 * Shared by {@see PhpNamespaceCache} and {@see PhpScanIndexCache}.
 *
 * @internal
 */
final class LockedPhpCacheWriter
{
    /**
     * @param callable(): array<string, mixed> $buildPayloadWhileLocked
     *
     * @return bool `true` when the payload was written, `false` on any I/O failure
     */
    public static function write(string $cacheFile, callable $buildPayloadWhileLocked): bool
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            $oldUmask = umask(0);
            @mkdir($dir, 0755, true);
            umask($oldUmask);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        $handle = @fopen($cacheFile . '.lock', 'c');
        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        try {
            return FileCache::writeContentsAtomically(
                $cacheFile,
                '<?php return ' . var_export($buildPayloadWhileLocked(), true) . ';',
            );
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
