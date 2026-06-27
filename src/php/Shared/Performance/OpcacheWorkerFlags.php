<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

/**
 * Builds the `-d` CLI flags that make a spawned PHP process share a compiled-code
 * cache with its siblings through an on-disk OPcache file cache.
 *
 * Parallel test workers each `require` the same compiled `.php` stdlib; with CLI
 * OPcache off (the default) every worker re-parses them. Pointing the pool at one
 * file cache lets worker N reuse what worker 1 compiled.
 *
 * Pure: callers pass OPcache availability and the resolved cache dir so it stays
 * trivially testable. Returns no flags (plain workers) when OPcache is absent or
 * no cache dir is available — PHP aborts at startup if `opcache.file_cache` is an
 * empty/missing path, so degrading is safer than emitting a half-set flag.
 */
final class OpcacheWorkerFlags
{
    /**
     * @return list<string> `-d` flag pairs, or an empty list to spawn plain workers
     */
    public static function forFileCache(bool $opcacheLoaded, string $fileCacheDir): array
    {
        if (!$opcacheLoaded || $fileCacheDir === '') {
            return [];
        }

        return ['-d', 'opcache.enable_cli=1', '-d', 'opcache.file_cache=' . $fileCacheDir];
    }
}
