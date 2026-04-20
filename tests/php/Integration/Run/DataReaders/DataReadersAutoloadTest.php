<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\DataReaders;

use Phel\Run\Infrastructure\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;

use function ob_get_clean;
use function ob_start;

final class DataReadersAutoloadTest extends AbstractTestCommand
{
    public function test_it_registers_tags_from_data_readers_file(): void
    {
        $output = $this->captureRunOutput(__DIR__ . '/Fixtures/consumer.phel');

        self::assertStringContainsString('HELLO', $output);
    }

    private function captureRunOutput(string $path): string
    {
        ob_start();
        (new RunCommand())->run(
            $this->stubInput($path),
            $this->stubOutput(),
        );

        return ob_get_clean() ?: '';
    }

    private function stubInput(string $path): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturnCallback(
            static fn(string $name): string|array => match ($name) {
                'path' => $path,
                'argv' => [],
                default => '',
            },
        );

        return $input;
    }
}
