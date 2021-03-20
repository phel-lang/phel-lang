<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Format;

use Gacela\Config;
use Phel\Command\CommandFactory;
use PHPUnit\Framework\TestCase;

final class FormatCommandTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/Fixtures/';

    protected function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function testGoodFormat(): void
    {
        $path = self::FIXTURES_DIR . 'good-format.phel';
        $oldContent = file_get_contents($path);

        $command = $this
            ->createCommandFactory()
            ->createFormatCommand();

        $this->expectOutputRegex('/No files were formatted+/s');

        try {
            $command->run([$path]);
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    public function testBadFormat(): void
    {
        $path = self::FIXTURES_DIR . 'bad-format.phel';
        $oldContent = file_get_contents($path);

        $command = $this
            ->createCommandFactory()
            ->createFormatCommand();

        $this->expectOutputString(<<<TXT
Formatted files:
  1) $path

TXT);
        try {
            $command->run([$path]);
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    private function createCommandFactory(): CommandFactory
    {
        return new CommandFactory();
    }
}
