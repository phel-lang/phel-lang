<?php

declare(strict_types=1);

namespace PhelTest\Support;

use function is_dir;
use function is_file;
use function mkdir;
use function putenv;
use function sys_get_temp_dir;
use function unlink;

/**
 * Shared state for {@see PerTestGacelaCacheExtension}: isolates Gacela's
 * per-project config cache between tests.
 *
 * Gacela (and Phel via it) defaults to an empty cache dir, which resolves to a
 * single shared file under the system temp dir. Gacela >= 1.15 keeps the merged
 * app config there, so a stale config (e.g. another test's source directories)
 * leaks between tests in the same run — and across `RunInSeparateProcess`
 * children and `exec(bin/phel ...)` subprocesses, since the file lives on disk —
 * breaking later command tests with "namespace not found in source directory".
 *
 * We point `GACELA_CACHE_DIR` at a stable per-run directory and delete only the
 * project-specific `gacela-merged-config.php` before each test. The
 * class/service caches (which are project-independent) persist, so Gacela never
 * has to recreate the whole cache dir — avoiding the write races that a fresh
 * empty dir per test would trigger.
 *
 * (The class is named without a `gacela*` prefix on purpose: the repo's
 * `.gitignore` ignores `gacela*.php`, which would silently drop this file.)
 */
final class PerTestGacelaCache
{
    private const string MERGED_CONFIG_FILE = 'gacela-merged-config.php';

    public function isolate(): void
    {
        $dir = $this->cacheDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        putenv('GACELA_CACHE_DIR=' . $dir);
        $_ENV['GACELA_CACHE_DIR'] = $dir;
        $_SERVER['GACELA_CACHE_DIR'] = $dir;

        $merged = $dir . '/' . self::MERGED_CONFIG_FILE;
        if (is_file($merged)) {
            unlink($merged);
        }
    }

    private function cacheDir(): string
    {
        return sys_get_temp_dir() . '/phel-phpunit-gacela-cache';
    }
}
