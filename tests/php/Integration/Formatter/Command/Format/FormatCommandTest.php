<?php

declare(strict_types=1);

namespace PhelTest\Integration\Formatter\Command\Format;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Formatter\FormatterConfig;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FormatCommandTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/Fixtures/';

    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_good_format(): void
    {
        $path = self::FIXTURES_DIR . 'good-format.phel';
        $oldContent = file_get_contents($path);

        $tester = new CommandTester($this->createFormatCommand());

        try {
            $tester->execute(['paths' => [$path]]);

            $this->assertMatchesRegularExpression('/No files were formatted+/s', $tester->getDisplay());
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    public function test_command_uses_default_paths(): void
    {
        $path = self::FIXTURES_DIR . 'bad-format.phel';
        $oldContent = file_get_contents($path);

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($path): void {
            $config->addAppConfigKeyValue(FormatterConfig::FORMAT_DIRS, [$path]);
        });

        $tester = new CommandTester($this->createFormatCommand());

        $expectedOutput = <<<TXT
Formatted files:
  1) {$path}

TXT;

        try {
            $tester->execute([]);

            $this->assertSame($expectedOutput, $tester->getDisplay());
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    public function test_bad_format(): void
    {
        $path = self::FIXTURES_DIR . 'bad-format.phel';
        $oldContent = file_get_contents($path);

        $tester = new CommandTester($this->createFormatCommand());

        $expectedOutput = <<<TXT
Formatted files:
  1) {$path}

TXT;

        try {
            $tester->execute([
                'paths' => [$path],
            ]);

            $this->assertSame($expectedOutput, $tester->getDisplay());
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    private function createFormatCommand(): FormatCommand
    {
        return new FormatCommand();
    }
}
