<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command;

use Phel\Command\CommandFactory;
use Phel\Command\CommandFactoryInterface;
use Phel\Compiler\CompilerFactory;
use Phel\Formatter\FormatterFactory;
use PHPUnit\Framework\TestCase;

final class FormatCommandTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/Fixtures/test-fmt/';

    public function testGoodFormat(): void
    {
        $path = self::FIXTURES_DIR . 'good-format.phel';
        $oldContent = file_get_contents($path);

        $command = $this
            ->createCommandFactory(self::FIXTURES_DIR)
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
            ->createCommandFactory(self::FIXTURES_DIR)
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

    private function createCommandFactory(string $currentDir): CommandFactoryInterface
    {
        $compilerFactory = new CompilerFactory();

        return new CommandFactory(
            $currentDir,
            $compilerFactory,
            new FormatterFactory($compilerFactory)
        );
    }
}
