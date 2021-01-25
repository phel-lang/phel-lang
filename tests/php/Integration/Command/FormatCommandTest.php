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
    public function testGoodFormat(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-fmt/';

        $command = $this
            ->createCommandFactory($currentDir)
            ->createFormatCommand();

        $this->expectOutputRegex('/No files were formatted+/s');
        $command->run([$currentDir . 'good-format.phel']);
    }

    public function testBadFormat(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-fmt/';
        $command = $this
            ->createCommandFactory($currentDir)
            ->createFormatCommand();

        $path = $currentDir . 'bad-format.phel';
        $oldContent = file_get_contents($path);

        $this->expectOutputString(<<<TXT
Formatted files:
  1) $path \n
TXT);
        $command->run([$path]);

        file_put_contents($path, $oldContent);
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
