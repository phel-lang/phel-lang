<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Run\Application\ReplHistoryPathResolver;
use Phel\Shared\PhelProjectDirectory;
use PHPUnit\Framework\TestCase;

final class ReplHistoryPathResolverTest extends TestCase
{
    private string $projectRoot = '';

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/phel-repl-' . uniqid('', true);
        mkdir($this->projectRoot);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->projectRoot);
    }

    public function test_returns_new_path_and_creates_phel_directory(): void
    {
        $resolver = new ReplHistoryPathResolver($this->projectRoot);

        $path = $resolver->resolve();

        self::assertSame(
            $this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME . '/' . ReplHistoryPathResolver::FILENAME,
            $path,
        );
        self::assertDirectoryExists($this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME);
    }

    public function test_leaves_a_legacy_history_file_where_it_is(): void
    {
        $legacy = $this->projectRoot . '/.phel-repl-history';
        file_put_contents($legacy, "(println :foo)\n");

        $path = new ReplHistoryPathResolver($this->projectRoot)->resolve();

        self::assertFileExists($legacy);
        self::assertFileDoesNotExist($path);
    }

    public function test_keeps_an_existing_history_file_untouched(): void
    {
        $phelDir = PhelProjectDirectory::ensure($this->projectRoot);
        $newPath = $phelDir . '/' . ReplHistoryPathResolver::FILENAME;
        file_put_contents($newPath, "kept\n");

        $resolved = new ReplHistoryPathResolver($this->projectRoot)->resolve();

        self::assertSame($newPath, $resolved);
        self::assertSame("kept\n", file_get_contents($newPath));
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $this->removeTree($path . '/' . $entry);
        }

        rmdir($path);
    }
}
