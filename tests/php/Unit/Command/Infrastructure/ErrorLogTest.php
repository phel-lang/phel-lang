<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Infrastructure;

use Phel\Command\Infrastructure\ErrorLog;
use Phel\Shared\ColorStyle;
use Phel\Shared\PhelProjectDirectory;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function substr_count;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

final class ErrorLogTest extends TestCase
{
    use RemoveDirTrait;

    private const string HEADER_PATTERN = '\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}\] phel run src/main\.phel';

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phel-error-log-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function test_it_writes_the_entry_with_a_trailing_newline(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'error.log';

        $this->errorLog($filepath)->writeln('hello');

        self::assertMatchesRegularExpression(
            '~^' . self::HEADER_PATTERN . '\Rhello\R$~',
            (string) file_get_contents($filepath),
        );
    }

    public function test_each_entry_opens_with_a_timestamp_and_the_command_line(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'error.log';
        $errorLog = $this->errorLog($filepath);

        $errorLog->writeln('hello');
        $errorLog->writeln('again');

        $contents = (string) file_get_contents($filepath);
        self::assertSame(2, substr_count($contents, '] phel run src/main.phel'));
        self::assertMatchesRegularExpression(
            '~^' . self::HEADER_PATTERN . '\Rhello\R' . self::HEADER_PATTERN . '\Ragain\R$~',
            $contents,
        );
    }

    public function test_it_strips_terminal_escapes(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'error.log';
        $colored = ColorStyle::withStyles()->blue('TypeError: boom') . PHP_EOL . 'in main.phel:3';

        $this->errorLog($filepath)->writeln($colored);

        $contents = (string) file_get_contents($filepath);
        self::assertStringNotContainsString("\033", $contents);
        self::assertStringContainsString('TypeError: boom' . PHP_EOL . 'in main.phel:3', $contents);
    }

    public function test_it_rotates_at_the_cap_and_keeps_one_previous_generation(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'error.log';
        $errorLog = new ErrorLog($filepath, 'phel run src/main.phel', maxBytes: 1);

        $errorLog->writeln('first');
        $errorLog->writeln('second');
        $errorLog->writeln('third');

        $contents = (string) file_get_contents($filepath);
        self::assertStringContainsString('third', $contents);
        self::assertStringNotContainsString('second', $contents);
        self::assertStringNotContainsString('first', $contents);

        $previousGeneration = (string) file_get_contents($filepath . '.1');
        self::assertStringContainsString('second', $previousGeneration);
        self::assertStringNotContainsString('first', $previousGeneration);

        self::assertFileDoesNotExist($filepath . '.2');
    }

    public function test_it_appends_below_the_cap(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'error.log';
        $errorLog = $this->errorLog($filepath);

        $errorLog->writeln('first');
        $errorLog->writeln('second');

        self::assertStringContainsString(
            'first' . PHP_EOL,
            (string) file_get_contents($filepath),
        );
        self::assertStringEndsWith('second' . PHP_EOL, (string) file_get_contents($filepath));
        self::assertFileDoesNotExist($filepath . '.1');
    }

    public function test_it_creates_missing_parent_directory(): void
    {
        $filepath = $this->tmpDir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'error.log';

        self::assertDirectoryDoesNotExist(dirname($filepath));

        $this->errorLog($filepath)->writeln('line');

        self::assertDirectoryExists(dirname($filepath));
        self::assertStringContainsString('line' . PHP_EOL, (string) file_get_contents($filepath));
    }

    public function test_it_routes_phel_project_directory_through_shared_helper(): void
    {
        $phelDir = $this->tmpDir . DIRECTORY_SEPARATOR . PhelProjectDirectory::DIRECTORY_NAME;
        $filepath = $phelDir . DIRECTORY_SEPARATOR . 'error.log';

        $this->errorLog($filepath)->writeln('logged');

        // The shared helper seeds a .gitignore alongside the log file.
        self::assertStringContainsString('logged' . PHP_EOL, (string) file_get_contents($filepath));
        self::assertFileExists($phelDir . DIRECTORY_SEPARATOR . '.gitignore');
    }

    private function errorLog(string $filepath): ErrorLog
    {
        return new ErrorLog($filepath, 'phel run src/main.phel');
    }
}
