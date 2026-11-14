<?php

declare(strict_types=1);

namespace PhelTest\Support;

use Gacela\Framework\Config\MergedConfigCache;

use function glob;
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
 * Class and service caches persist; merged configuration must not carry
 * another test's source or temporary directories into the next test.
 */
final class PerTestGacelaCache
{
    public function isolate(): void
    {
        $dir = $this->cacheDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        putenv('GACELA_CACHE_DIR=' . $dir);
        $_ENV['GACELA_CACHE_DIR'] = $dir;
        $_SERVER['GACELA_CACHE_DIR'] = $dir;

        foreach (glob($dir . '/' . MergedConfigCache::FILENAME_PREFIX . '*.php') ?: [] as $merged) {
            if (is_file($merged)) {
                unlink($merged);
            }
        }
    }

    private function cacheDir(): string
    {
        return sys_get_temp_dir() . '/phel-phpunit-gacela-cache';
    }
}
