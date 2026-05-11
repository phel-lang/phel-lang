<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Filesystem\Application\PhelProjectDirectory;
use Phel\Run\Application\ReplHistoryPathResolver;
use PHPUnit\Framework\TestCase;

use function is_resource;

final class ReplHistoryPathResolverTest extends TestCase
{
    private string $projectRoot = '';

    /** @var resource */
    private $stderr;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/phel-repl-' . uniqid('', true);
        mkdir($this->projectRoot);
        $this->stderr = fopen('php://memory', 'w+b');
        putenv(ReplHistoryPathResolver::QUIET_ENV);
    }

    protected function tearDown(): void
    {
        putenv(ReplHistoryPathResolver::QUIET_ENV);
        if (is_resource($this->stderr)) {
            fclose($this->stderr);
        }

        $this->removeTree($this->projectRoot);
    }

    public function test_returns_new_path_and_creates_phel_directory(): void
    {
        $resolver = new ReplHistoryPathResolver($this->projectRoot, $this->stderr);

        $path = $resolver->resolve();

        self::assertSame(
            $this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME . '/' . ReplHistoryPathResolver::FILENAME,
            $path,
        );
        self::assertDirectoryExists($this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME);
        self::assertSame('', $this->capturedStderr());
    }

    public function test_migrates_legacy_history_and_emits_deprecation(): void
    {
        $legacy = $this->projectRoot . '/' . ReplHistoryPathResolver::LEGACY_FILENAME;
        file_put_contents($legacy, "(println :foo)\n");

        $path = new ReplHistoryPathResolver($this->projectRoot, $this->stderr)->resolve();

        self::assertFileDoesNotExist($legacy);
        self::assertFileExists($path);
        self::assertSame("(println :foo)\n", file_get_contents($path));
        self::assertStringContainsString('migrated REPL history', $this->capturedStderr());
        self::assertStringContainsString(ReplHistoryPathResolver::QUIET_ENV, $this->capturedStderr());
    }

    public function test_quiet_env_silences_deprecation_notice(): void
    {
        putenv(ReplHistoryPathResolver::QUIET_ENV . '=1');
        file_put_contents($this->projectRoot . '/' . ReplHistoryPathResolver::LEGACY_FILENAME, "x\n");

        new ReplHistoryPathResolver($this->projectRoot, $this->stderr)->resolve();

        self::assertSame('', $this->capturedStderr());
    }

    public function test_does_not_migrate_when_new_path_already_exists(): void
    {
        $phelDir = PhelProjectDirectory::ensure($this->projectRoot);
        $newPath = $phelDir . '/' . ReplHistoryPathResolver::FILENAME;
        file_put_contents($newPath, "kept\n");
        $legacy = $this->projectRoot . '/' . ReplHistoryPathResolver::LEGACY_FILENAME;
        file_put_contents($legacy, "stale\n");

        new ReplHistoryPathResolver($this->projectRoot, $this->stderr)->resolve();

        self::assertSame("kept\n", file_get_contents($newPath));
        self::assertSame("stale\n", file_get_contents($legacy));
        self::assertSame('', $this->capturedStderr());
    }

    private function capturedStderr(): string
    {
        rewind($this->stderr);
        return (string) stream_get_contents($this->stderr);
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
