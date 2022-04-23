<?php

declare(strict_types=1);

namespace PhelTest\Integration\Formatter\Command\Format;

use Gacela\Framework\Gacela;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

        $command = $this->createFormatCommand();

        $this->expectOutputRegex('/No files were formatted+/s');

        try {
            $command->run(
                $this->stubInput([$path]),
                $this->stubOutput()
            );
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    public function test_bad_format(): void
    {
        $path = self::FIXTURES_DIR . 'bad-format.phel';
        $oldContent = file_get_contents($path);

        $command = $this->createFormatCommand();

        $this->expectOutputString(
            <<<TXT
Formatted files:
  1) ${path}

TXT
        );
        try {
            $command->run(
                $this->stubInput([$path]),
                $this->stubOutput()
            );
        } finally {
            file_put_contents($path, $oldContent);
        }
    }

    private function createFormatCommand(): FormatCommand
    {
        return new FormatCommand();
    }

    private function stubInput(array $paths): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($paths);

        return $input;
    }

    private function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(static fn (string $str) => print $str . PHP_EOL);

        return $output;
    }
}
