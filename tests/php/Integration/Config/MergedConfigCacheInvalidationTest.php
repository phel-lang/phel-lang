<?php

declare(strict_types=1);

namespace PhelTest\Integration\Config;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Testing\ContainerFixture;
use Phel\Config\PhelConfig;
use Phel\Phel;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function uniqid;

final class MergedConfigCacheInvalidationTest extends TestCase
{
    use ContainerFixture;

    private string $projectDir = '';

    protected function setUp(): void
    {
        $this->resetContainer();
        $this->projectDir = sys_get_temp_dir() . '/phel-merged-config-' . uniqid('', true);
        mkdir($this->projectDir);
        Phel::resetAutoDetectedConfig();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
        Phel::resetAutoDetectedConfig();
        $this->resetContainer();
    }

    public function test_editing_phel_config_invalidates_the_persisted_merged_cache(): void
    {
        $this->writeConfig(['first/dir']);
        Phel::bootstrap($this->projectDir);
        self::assertSame(['first/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));

        // The merged config is now persisted on disk under the project's
        // .phel/cache. Re-bootstrapping with a changed config must pick up the
        // new value instead of replaying the stale cached one.
        $this->resetContainer();
        $this->writeConfig(['second/dir']);
        Phel::bootstrap($this->projectDir);

        self::assertSame(['second/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));
    }

    public function test_unchanged_config_keeps_returning_values_on_cache_hit(): void
    {
        $this->writeConfig(['stable/dir']);
        Phel::bootstrap($this->projectDir);
        self::assertSame(['stable/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));

        // Re-bootstrapping with an unchanged config takes the fast path
        // (fingerprint matches, the persisted cache is reused) and must still
        // expose the cached values rather than an empty or broken config.
        $this->resetContainer();
        Phel::bootstrap($this->projectDir);

        self::assertSame(['stable/dir'], Config::getInstance()->get(PhelConfig::SRC_DIRS));
    }

    /**
     * @param list<string> $srcDirs
     */
    private function writeConfig(array $srcDirs): void
    {
        $list = "'" . implode("', '", $srcDirs) . "'";
        file_put_contents(
            $this->projectDir . '/' . Phel::PHEL_CONFIG_FILE_NAME,
            <<<PHP
            <?php
            use Phel\\Config\\PhelConfig;
            return (new PhelConfig())->withSrcDirs([{$list}]);
            PHP,
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
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
