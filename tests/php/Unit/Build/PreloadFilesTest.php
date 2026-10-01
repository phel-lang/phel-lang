<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build;

use PHPUnit\Framework\TestCase;

use function dirname;

final class PreloadFilesTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 4);
        require_once $this->projectRoot . '/build/preload-files.php';
    }

    public function test_every_listed_file_exists(): void
    {
        foreach (phelPreloadFiles($this->projectRoot) as $relative) {
            self::assertFileExists($this->projectRoot . $relative);
        }
    }

    public function test_every_module_with_a_facade_is_listed(): void
    {
        $listed = phelPreloadFiles($this->projectRoot);
        $facades = glob($this->projectRoot . '/src/php/*/*Facade.php') ?: [];

        self::assertNotEmpty($facades);
        foreach ($facades as $facade) {
            $module = basename(dirname($facade));
            if (basename($facade) !== $module . 'Facade.php') {
                continue;
            }

            self::assertContains('/src/php/' . $module . '/' . $module . 'Facade.php', $listed);
        }
    }

    public function test_lists_registry_and_entry_point(): void
    {
        $listed = phelPreloadFiles($this->projectRoot);

        self::assertContains('/src/php/Lang/Registry.php', $listed);
        self::assertContains('/src/Phel.php', $listed);
    }
}
