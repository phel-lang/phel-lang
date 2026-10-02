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
use function scandir;
use function sort;
use function str_ends_with;
use function str_starts_with;

/**
 * Keeps Gacela's persisted merged-config cache in sync with its inputs.
 *
 * Gacela (2.5+) checks the cache it writes on a miss against the config files,
 * but trusts one written by `phel cache:warm` until the next warm or clear, and
 * never watches Phel's config classes, whose serialized values it caches. This
 * class fingerprints both and, when the fingerprint changes, clears the cache
 * and triggers a reload so the current values take effect. When nothing
 * changed it is a handful of stat/hash calls and the cache is reused.
 *
 * It depends only on Gacela's public `MergedConfigCache` API and on an injected
 * reload callback, so the whole flow is exercisable without a live bootstrap.
 *
 * @see Phel::bootstrap()
 *
 * @internal
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

    /**
     * Every `phel-config*.php` in the project root. Gacela reads not only
     * `phel-config.php` and `phel-config-local.php` but a `phel-config-<suffix>.php`
     * per `APP_ENV` and config dimension, so a fixed list misses an edit there.
     * A file appearing or disappearing changes the list, and with it the
     * fingerprint.
     *
     * @return list<string>
     */
    public static function projectConfigFiles(string $appRootDir, string $baseName): array
    {
        // Not glob(): the root is a path, and `[` or `?` in it would be read
        // as pattern syntax.
        $prefix = basename($baseName, '.php');
        $files = [];
        foreach (@scandir($appRootDir) ?: [] as $entry) {
            if (str_starts_with($entry, $prefix) && str_ends_with($entry, '.php') && is_file($appRootDir . '/' . $entry)) {
                $files[] = $appRootDir . '/' . $entry;
            }
        }

        sort($files);

        return $files;
    }

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
