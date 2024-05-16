<?php

declare(strict_types=1);

namespace PhelTest\Integration\Formatter\Command\Format;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Config\PhelConfig;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FormatCommandTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/Fixtures/';

    public function test_good_format(): void
    {
        Gacela::bootstrap(__DIR__);

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
        Gacela::bootstrap(__DIR__);

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

    private function createCommandTester(): CommandTester
    {
        return new CommandTester(new FormatCommand());
    }
}
