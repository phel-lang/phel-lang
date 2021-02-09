<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Format;

use Phel\Command\CommandConfigInterface;
use Phel\Command\CommandFactory;
use Phel\Command\CommandFactoryInterface;
use Phel\Compiler\CompilerFactory;
use Phel\Formatter\FormatterFactory;
use Phel\Interop\InteropFactoryInterface;
use PHPUnit\Framework\TestCase;

final class FormatCommandTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/Fixtures/';

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
            $this->createStub(CommandConfigInterface::class),
            $compilerFactory,
            new FormatterFactory($compilerFactory),
            $this->createStub(InteropFactoryInterface::class)
        );
    }
}
