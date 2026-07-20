<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Gacela\Framework\Config\AppEnv;
use Gacela\Framework\Config\MergedConfigCache;

use function basename;
use function dirname;
use function implode;
use function is_dir;
use function is_file;
use function md5;
use function md5_file;
use function preg_replace;

/**
 * Keeps Gacela's persisted merged-config cache (1.15+) in sync with its inputs.
 *
 * Gacela auto-warms the merged app config to `gacela-merged-config.php` on the
 * first bootstrap and then reloads it unconditionally, with no freshness check.
 * This class fingerprints the files that determine the merged config and, when
 * the fingerprint changes, clears the cache and triggers a reload so the current
 * values take effect. When nothing changed it is a handful of stat/hash calls
 * and the cache is reused as intended.
 *
 * It depends only on Gacela's public `MergedConfigCache` API and on an injected
 * reload callback, so the whole flow is exercisable without a live bootstrap.
 *
 * @see Phel::bootstrap()
 */
final readonly class MergedConfigCacheInvalidator
{
    /**
     * @param string         $cacheDir          The dir where Gacela persists the merged-config cache
     * @param string         $appRootDir        The app root Gacela scopes the cache filename to (1.18+);
     *                                          must match the dir passed to `Gacela::bootstrap()`
     * @param list<string>   $fingerprintInputs Absolute paths whose contents determine the merged
     *                                          config (project config files + config data-model classes)
     * @param Closure():void $reloadConfig      Reloads Gacela's config from source after a cache clear
     */
    public function __construct(
        private string $cacheDir,
        private string $appRootDir,
        private array $fingerprintInputs,
        private Closure $reloadConfig,
    ) {}

    public function refreshIfStale(): void
    {
        $cache = $this->mergedConfigCache();
        $fingerprintFile = preg_replace('/\.php$/', '.fingerprint', $cache->filename());
        if ($fingerprintFile === null) {
            return;
        }

        $current = $this->fingerprint();
        $stored = is_file($fingerprintFile) ? @file_get_contents($fingerprintFile) : null;
        if ($stored === $current) {
            return;
        }

        $cache->clear();
        ($this->reloadConfig)();

        if (is_dir(dirname($fingerprintFile))) {
            @file_put_contents($fingerprintFile, $current);
        }
    }

    /**
     * Content hash over every cache input. Any change to a config file or to a
     * config data-model class (whose keys define the wire format) flips it.
     */
    public function fingerprint(): string
    {
        $parts = [];
        foreach ($this->fingerprintInputs as $path) {
            $parts[] = basename($path) . ':' . $this->fileHash($path);
        }

        return md5(implode('|', $parts));
    }

    /**
     * Rebuild Gacela's merged-config cache handle from public API only, mirroring
     * how Gacela constructs it: the resolved cache dir, the optional `APP_ENV`
     * suffix, and the app root the filename is scoped to since Gacela 1.18.
     */
    private function mergedConfigCache(): MergedConfigCache
    {
        return new MergedConfigCache($this->cacheDir, AppEnv::current(), $this->appRootDir);
    }

    /**
     * Stable content hash of a single cache-input file, or a placeholder when it
     * is absent or unreadable.
     */
    private function fileHash(string $path): string
    {
        if (!is_file($path)) {
            return '-';
        }

        return md5_file($path) ?: '-';
    }
}
