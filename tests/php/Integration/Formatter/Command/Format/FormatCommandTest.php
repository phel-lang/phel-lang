<?php

declare(strict_types=1);

namespace PhelTest\Integration\Formatter\Command\Format;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Config\PhelConfig;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Phel\Phel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function sys_get_temp_dir;
use function uniqid;

final class FormatCommandTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/Fixtures/';

    public function test_good_format(): void
    {
        Phel::bootstrap(__DIR__);

        $path = self::FIXTURES_DIR . 'good-format.phel';
        $oldContent = file_get_contents($path);

        try {
            $tester = $this->createCommandTester();
            $tester->execute(['paths' => [$path]]);

            $this->assertMatchesRegularExpression('/No files were formatted+/s', $tester->getDisplay());
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    public function test_bad_format(): void
    {
        $path = self::FIXTURES_DIR . 'bad-format.phel';
        $oldContent = file_get_contents($path);

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($path): void {
            $config->addAppConfigKeyValue(PhelConfig::FORMAT_DIRS, [$path]);
        });

        $expectedOutput = <<<TXT
Formatted files:
  1) {$path}

TXT;
        try {
            $tester = $this->createCommandTester();
            $tester->execute([]);

            self::assertSame($expectedOutput, $tester->getDisplay());
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    public function test_command_uses_default_paths(): void
    {
        Phel::bootstrap(__DIR__);

        $path = self::FIXTURES_DIR . 'bad-format.phel';
        $oldContent = file_get_contents($path);

        $expectedOutput = <<<TXT
Formatted files:
  1) {$path}

TXT;
        try {
            $tester = $this->createCommandTester();
            $tester->execute(['paths' => [$path]]);

            self::assertSame($expectedOutput, $tester->getDisplay());
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    public function test_unparsable_file_exits_non_zero(): void
    {
        Phel::bootstrap(__DIR__);

        $path = $this->writeTemporaryPhelFile("(ns phel-test.formatter.broken)\n\n(defn broken [\n");

        try {
            $tester = $this->createCommandTester();
            $exitCode = $tester->execute(['paths' => [$path]]);

            self::assertSame(Command::FAILURE, $exitCode, $tester->getDisplay());
            self::assertStringContainsString('could not be formatted', $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }

    public function test_unparsable_file_exits_non_zero_on_dry_run(): void
    {
        Phel::bootstrap(__DIR__);

        $path = $this->writeTemporaryPhelFile("(ns phel-test.formatter.broken)\n\n(defn broken [\n");

        try {
            $tester = $this->createCommandTester();
            $exitCode = $tester->execute(['paths' => [$path], '--dry-run' => true]);

            self::assertSame(Command::FAILURE, $exitCode, $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }

    /**
     * The fixture is unparsable on purpose, so it must not live inside the
     * repository: `phel format` walks `tests/` and would trip over it.
     */
    private function writeTemporaryPhelFile(string $contents): string
    {
        $path = sys_get_temp_dir() . '/phel-format-' . uniqid() . '.phel';
        file_put_contents($path, $contents);

        return $path;
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester(new FormatCommand());
    }
}
