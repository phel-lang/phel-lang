<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use function dirname;
use function function_exists;

/**
 * Writes a `var_export`ed PHP payload to a cache file through an exclusive
 * `flock`, creating the parent directory on demand and invalidating the
 * opcache entry afterwards. The payload is computed while the lock is held,
 * so callers can safely merge the current on-disk state into their in-memory
 * entries before serializing.
 *
 * Shared by {@see PhpNamespaceCache} and {@see PhpScanIndexCache}.
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

        $handle = @fopen($cacheFile, 'c');
        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        try {
            $content = '<?php return ' . var_export($buildPayloadWhileLocked(), true) . ';';
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $content);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($cacheFile, true);
        }

        return true;
    }
}
