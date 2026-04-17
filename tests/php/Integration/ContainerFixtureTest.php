<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Gacela\Framework\Testing\ContainerFixture;
use Phel\Filesystem\FilesystemFacade;
use PHPUnit\Framework\TestCase;

final class ContainerFixtureTest extends TestCase
{
    use ContainerFixture;

    protected function setUp(): void
    {
        $this->resetContainer();
    }

    protected function tearDown(): void
    {
        $this->cleanupContainerTempDirs();
    }

    public function test_reset_container_clears_resolved_facades(): void
    {
        Gacela::bootstrap(__DIR__);
        $before = Gacela::get(FilesystemFacade::class);
        self::assertInstanceOf(FilesystemFacade::class, $before);

        $this->resetContainer();
        Gacela::bootstrap(__DIR__);
        $after = Gacela::get(FilesystemFacade::class);

        self::assertNotSame($before, $after);
    }

    public function test_capture_and_restore_container_state(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue('test-key', 'test-value');
        });

        $snapshot = $this->captureContainerState();

        $this->resetContainer();
        $afterReset = $this->captureContainerState();
        self::assertEmpty($afterReset->config());

        $this->restoreContainerState($snapshot);
        $restored = $this->captureContainerState();
        self::assertSame($snapshot->inMemoryCache(), $restored->inMemoryCache());
    }

    public function test_container_temp_dir_creates_unique_directory(): void
    {
        $dir1 = $this->containerTempDir();
        $dir2 = $this->containerTempDir();

        self::assertDirectoryExists($dir1);
        self::assertDirectoryExists($dir2);
        self::assertNotSame($dir1, $dir2);
    }

    public function test_cleanup_container_temp_dirs_removes_directories(): void
    {
        $dir = $this->containerTempDir();
        file_put_contents($dir . '/test.txt', 'data');
        mkdir($dir . '/nested', 0777, true);
        file_put_contents($dir . '/nested/deep.txt', 'deep');

        self::assertDirectoryExists($dir);

        $this->cleanupContainerTempDirs();

        self::assertDirectoryDoesNotExist($dir);
    }
}
