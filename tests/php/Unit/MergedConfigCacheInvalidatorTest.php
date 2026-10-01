<?php

declare(strict_types=1);

namespace PhelTest\Unit;

use Closure;
use Gacela\Framework\Config\MergedConfigCache;
use Phel\MergedConfigCacheInvalidator;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

final class MergedConfigCacheInvalidatorTest extends TestCase
{
    use RemoveDirTrait;

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

    public function test_project_config_files_include_env_and_dimension_variants(): void
    {
        foreach (['phel-config.php', 'phel-config-local.php', 'phel-config-prod.php', 'other.php'] as $file) {
            file_put_contents($this->dir . '/' . $file, '<?php return [];');
        }

        self::assertSame(
            [
                $this->dir . '/phel-config-local.php',
                $this->dir . '/phel-config-prod.php',
                $this->dir . '/phel-config.php',
            ],
            MergedConfigCacheInvalidator::projectConfigFiles($this->dir, 'phel-config.php'),
        );
    }

    public function test_project_config_files_read_a_root_with_glob_characters_literally(): void
    {
        $root = $this->dir . '/app[1]?';
        mkdir($root);
        file_put_contents($root . '/phel-config.php', '<?php return [];');

        self::assertSame(
            [$root . '/phel-config.php'],
            MergedConfigCacheInvalidator::projectConfigFiles($root, 'phel-config.php'),
        );
    }

    public function test_an_edit_to_an_env_config_flips_the_fingerprint(): void
    {
        file_put_contents($this->dir . '/phel-config.php', '<?php return [];');
        file_put_contents($this->dir . '/phel-config-prod.php', "<?php return ['src-dirs' => ['prod1']];");
        $before = $this->invalidator(MergedConfigCacheInvalidator::projectConfigFiles($this->dir, 'phel-config.php'))->fingerprint();

        file_put_contents($this->dir . '/phel-config-prod.php', "<?php return ['src-dirs' => ['prod2']];");
        $after = $this->invalidator(MergedConfigCacheInvalidator::projectConfigFiles($this->dir, 'phel-config.php'))->fingerprint();

        self::assertNotSame($before, $after);
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
        $cacheFile = $this->cacheFilename();
        file_put_contents($cacheFile, '<?php return [];');

        $reloads = 0;
        $this->invalidator([$input], static function () use (&$reloads): void {
            ++$reloads;
        })->refreshIfStale();

        self::assertSame(1, $reloads);
        self::assertFileDoesNotExist($cacheFile);
        self::assertFileExists($this->fingerprintFilename());
    }

    public function test_refresh_skips_when_fingerprint_matches(): void
    {
        $input = $this->dir . '/phel-config.php';
        file_put_contents($input, '<?php return 1;');
        $cacheFile = $this->cacheFilename();
        file_put_contents($cacheFile, '<?php return [];');

        $fresh = $this->invalidator([$input]);
        file_put_contents($this->fingerprintFilename(), $fresh->fingerprint());

        $reloads = 0;
        $this->invalidator([$input], static function () use (&$reloads): void {
            ++$reloads;
        })->refreshIfStale();

        self::assertSame(0, $reloads);
        self::assertFileExists($cacheFile);
    }

    public function test_refresh_targets_the_app_scoped_cache_file(): void
    {
        // The app-scoped filename (Gacela 1.18+) embeds a hash of the app root,
        // so it must differ from the legacy unscoped one the invalidator used
        // to address before it learned about the app root.
        self::assertNotSame($this->dir . '/gacela-merged-config.php', $this->cacheFilename());
        self::assertStringStartsWith($this->dir . '/gacela-merged-config-', $this->cacheFilename());
    }

    /**
     * The exact cache filename Gacela computes for this cache dir and app root.
     */
    private function cacheFilename(): string
    {
        return new MergedConfigCache($this->dir, '', $this->dir)->filename();
    }

    private function fingerprintFilename(): string
    {
        return (string) preg_replace('/\.php$/', '.fingerprint', $this->cacheFilename());
    }

    /**
     * @param list<string> $inputs
     */
    private function invalidator(array $inputs, ?Closure $reload = null): MergedConfigCacheInvalidator
    {
        return new MergedConfigCacheInvalidator(
            $this->dir,
            $this->dir,
            $inputs,
            $reload ?? static function (): void {},
        );
    }

}
