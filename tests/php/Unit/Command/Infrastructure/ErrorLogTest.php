<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Infrastructure;

use Phel\Command\Infrastructure\ErrorLog;
use Phel\Shared\PhelProjectDirectory;
use PHPUnit\Framework\TestCase;

use function dirname;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

final class ErrorLogTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phel-error-log-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->tmpDir);
    }

    public function test_it_writes_text_with_trailing_newline(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'error.log';
        $errorLog = new ErrorLog($filepath);

        $errorLog->writeln('hello');

        self::assertStringEqualsFile($filepath, 'hello' . PHP_EOL);
    }

    public function test_it_appends_subsequent_lines(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'error.log';
        $errorLog = new ErrorLog($filepath);

        $errorLog->writeln('first');
        $errorLog->writeln('second');

        self::assertStringEqualsFile(
            $filepath,
            'first' . PHP_EOL . 'second' . PHP_EOL,
        );
    }

    public function test_it_creates_missing_parent_directory(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'error.log';
        $errorLog = new ErrorLog($filepath);

        self::assertDirectoryDoesNotExist(dirname($filepath));

        $errorLog->writeln('line');

        self::assertDirectoryExists(dirname($filepath));
        self::assertStringEqualsFile($filepath, 'line' . PHP_EOL);
    }

    public function test_it_routes_phel_project_directory_through_shared_helper(): void
    {
        $projectRoot = $this->tmpDir;
        $phelDir = $projectRoot . DIRECTORY_SEPARATOR . PhelProjectDirectory::DIRECTORY_NAME;
        $filepath = $phelDir . DIRECTORY_SEPARATOR . 'error.log';
        $errorLog = new ErrorLog($filepath);

        $errorLog->writeln('logged');

        // The shared helper seeds a .gitignore alongside the log file.
        self::assertStringEqualsFile($filepath, 'logged' . PHP_EOL);
        self::assertFileExists($phelDir . DIRECTORY_SEPARATOR . '.gitignore');
    }

    private function removeRecursively(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path)) {
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

            $this->removeRecursively($path . DIRECTORY_SEPARATOR . $entry);
        }

        rmdir($path);
    }
}
