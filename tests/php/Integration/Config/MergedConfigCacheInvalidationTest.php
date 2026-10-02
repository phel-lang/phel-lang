<?php

declare(strict_types=1);

namespace PhelTest\Integration\Config;

use Gacela\Framework\Config\AppEnv;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\MergedConfigCache;
use Gacela\Framework\Testing\ContainerFixture;
use Phel\Config\PhelConfig;
use Phel\Phel;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function sys_get_temp_dir;
use function uniqid;

final class MergedConfigCacheInvalidationTest extends TestCase
{
    use RemoveDirTrait;

    use ContainerFixture;

    private const int BASE_MTIME_OFFSET = -100;

    private string $projectDir = '';

    private string|false $previousAppEnv = false;

    private int $lastConfigMtime = 0;

    protected function setUp(): void
    {
        $this->resetContainer();
        $this->projectDir = sys_get_temp_dir() . '/phel-merged-config-' . uniqid('', true);
        mkdir($this->projectDir);
        Phel::resetAutoDetectedConfig();
        $this->previousAppEnv = getenv('APP_ENV');
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        putenv($this->previousAppEnv === false ? 'APP_ENV' : 'APP_ENV=' . $this->previousAppEnv);
        $this->removeDir($this->projectDir);
        Phel::resetAutoDetectedConfig();
        $this->resetContainer();
    }

    public function test_editing_phel_config_invalidates_the_persisted_merged_cache(): void
    {
        $this->writeConfig(['first/dir']);
        $this->bootstrapUntilCached();
        self::assertSame(['first/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));

        // Re-bootstrapping with a changed config must pick up the new value
        // instead of replaying the stale cached one.
        $this->resetContainer();
        $this->writeConfig(['second/dir']);
        Phel::bootstrap($this->projectDir);

        self::assertSame(['second/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));
    }

    public function test_editing_phel_config_invalidates_a_warmed_merged_cache(): void
    {
        // Users run `phel cache:warm` while they still edit config, so the
        // warmed cache is a checked one, not a trusted deploy artifact.
        $this->writeConfig(['warm/dir']);
        $this->bootstrapUntilCached();
        $this->mergedConfigCache()->clear();
        Config::getInstance()->writeMergedConfigCache();
        self::assertTrue($this->mergedConfigCache()->exists());

        $this->resetContainer();
        $this->writeConfig(['edited/dir']);
        Phel::bootstrap($this->projectDir);

        self::assertSame(['edited/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));
    }

    public function test_a_changed_config_class_rebuilds_the_merged_cache(): void
    {
        // The cache holds what PhelConfig serialized, so a Phel upgrade that
        // changes the class must not be answered by a cache from before it.
        $configClass = (string) new ReflectionClass(PhelConfig::class)->getFileName();
        $originalMtime = (int) filemtime($configClass);
        $this->writeConfig(['stable/dir']);
        $this->bootstrapUntilCached();
        $before = (string) file_get_contents($this->mergedConfigCache()->filename());

        try {
            touch($configClass, $originalMtime - 7);
            clearstatcache(true, $configClass);
            $this->resetContainer();
            Phel::bootstrap($this->projectDir);
            Config::getInstance()->get(PhelConfig::SRC_DIRS);

            self::assertNotSame($before, (string) file_get_contents($this->mergedConfigCache()->filename()));
        } finally {
            touch($configClass, $originalMtime);
            clearstatcache(true, $configClass);
        }
    }

    public function test_unchanged_config_keeps_returning_values_on_cache_hit(): void
    {
        $this->writeConfig(['stable/dir']);
        Phel::bootstrap($this->projectDir);
        self::assertSame(['stable/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));

        // Re-bootstrapping with an unchanged config takes the fast path
        // (the persisted cache is reused) and must still
        // expose the cached values rather than an empty or broken config.
        $this->resetContainer();
        Phel::bootstrap($this->projectDir);

        self::assertSame(['stable/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));
    }

    public function test_editing_an_env_config_invalidates_the_persisted_merged_cache(): void
    {
        putenv('APP_ENV=prod');
        $this->writeConfig([]);
        $this->writeConfig(['prod1/dir'], 'prod');
        $this->bootstrapUntilCached();
        self::assertSame(['prod1/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));

        $this->resetContainer();
        $this->writeConfig(['prod2/dir'], 'prod');
        Phel::bootstrap($this->projectDir);

        self::assertSame(['prod2/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));
    }

    /**
     * Gacela does not persist config read from a path touched this second,
     * since its stamp could not tell a second edit in the same second. The
     * project dir is a watched path, so it is backdated like the config files.
     */
    private function bootstrapUntilCached(): void
    {
        touch($this->projectDir, self::BASE_MTIME_OFFSET + time());
        clearstatcache();
        Phel::bootstrap($this->projectDir);

        self::assertTrue($this->mergedConfigCache()->exists());
    }

    private function mergedConfigCache(): MergedConfigCache
    {
        $config = Config::getInstance();

        return new MergedConfigCache($config->getCacheDir(), AppEnv::current(), $config->getAppRootDir());
    }

    /**
     * @param list<string> $srcDirs
     */
    private function writeConfig(array $srcDirs, string $env = ''): void
    {
        $list = $srcDirs === [] ? '' : "'" . implode("', '", $srcDirs) . "'";
        $file = $this->projectDir . '/' . ($env === '' ? Phel::PHEL_CONFIG_FILE_NAME : 'phel-config-' . $env . '.php');
        file_put_contents(
            $file,
            <<<PHP
            <?php
            use Phel\\Config\\PhelConfig;
            return (new PhelConfig())->withSrcDirs([{$list}]);
            PHP,
        );

        // Each write lands in the past and later than the one before, so
        // Gacela can persist the cache and still tell the edit apart.
        $this->lastConfigMtime = $this->lastConfigMtime === 0 ? self::BASE_MTIME_OFFSET + time() : $this->lastConfigMtime + 10;
        touch($file, $this->lastConfigMtime);
        clearstatcache(true, $file);
    }
}
