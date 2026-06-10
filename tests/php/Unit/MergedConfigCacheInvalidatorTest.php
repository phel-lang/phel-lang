<?php

declare(strict_types=1);

namespace PhelTest\Unit;

use Closure;
use Phel\MergedConfigCacheInvalidator;
use PHPUnit\Framework\TestCase;

final class MergedConfigCacheInvalidatorTest extends TestCase
{
    private string $dir = '';

    private ?string $previousAppEnv = null;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phel-mcci-' . uniqid('', true);
        mkdir($this->dir);

        // Pin the cache filename (no env suffix) so the assertions are stable.
        $env = getenv('APP_ENV');
        $this->previousAppEnv = $env === false ? null : $env;
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        if ($this->previousAppEnv === null) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $this->previousAppEnv);
        }
    }

    public function test_fingerprint_changes_when_an_input_file_changes(): void
    {
        $input = $this->dir . '/phel-config.php';
        file_put_contents($input, '<?php return 1;');
        $invalidator = $this->invalidator([$input]);

        $before = $invalidator->fingerprint();
        file_put_contents($input, '<?php return 2;');

        self::assertNotSame($before, $invalidator->fingerprint());
    }

    public function test_fingerprint_is_stable_for_unchanged_inputs(): void
    {
        $input = $this->dir . '/phel-config.php';
        file_put_contents($input, '<?php return 1;');
        $invalidator = $this->invalidator([$input]);

        self::assertSame($invalidator->fingerprint(), $invalidator->fingerprint());
    }

    public function test_fingerprint_tolerates_missing_inputs(): void
    {
        $invalidator = $this->invalidator([$this->dir . '/absent.php']);

        self::assertNotSame('', $invalidator->fingerprint());
    }

    public function test_refresh_clears_cache_and_reloads_when_stale(): void
    {
        $input = $this->dir . '/phel-config.php';
        file_put_contents($input, '<?php return 1;');
        $cacheFile = $this->dir . '/gacela-merged-config.php';
        file_put_contents($cacheFile, '<?php return [];');

        $reloads = 0;
        $this->invalidator([$input], static function () use (&$reloads): void {
            ++$reloads;
        })->refreshIfStale();

        self::assertSame(1, $reloads);
        self::assertFileDoesNotExist($cacheFile);
        self::assertFileExists($this->dir . '/gacela-merged-config.fingerprint');
    }

    public function test_refresh_skips_when_fingerprint_matches(): void
    {
        $input = $this->dir . '/phel-config.php';
        file_put_contents($input, '<?php return 1;');
        $cacheFile = $this->dir . '/gacela-merged-config.php';
        file_put_contents($cacheFile, '<?php return [];');

        $fresh = $this->invalidator([$input]);
        file_put_contents($this->dir . '/gacela-merged-config.fingerprint', $fresh->fingerprint());

        $reloads = 0;
        $this->invalidator([$input], static function () use (&$reloads): void {
            ++$reloads;
        })->refreshIfStale();

        self::assertSame(0, $reloads);
        self::assertFileExists($cacheFile);
    }

    /**
     * @param list<string> $inputs
     */
    private function invalidator(array $inputs, ?Closure $reload = null): MergedConfigCacheInvalidator
    {
        return new MergedConfigCacheInvalidator(
            $this->dir,
            $inputs,
            $reload ?? static function (): void {},
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
