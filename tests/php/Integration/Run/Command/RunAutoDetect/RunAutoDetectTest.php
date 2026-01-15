<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\RunAutoDetect;

use Phel\Run\Infrastructure\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;

final class RunAutoDetectTest extends AbstractTestCommand
{
    public function test_auto_detect_entry_point(): void
    {
        $this->expectOutputRegex('~auto-detected core~');

        $this->createRunCommand()->run(
            $this->stubInputWithNoPath(),
            $this->stubOutput(),
        );
    }

    public function test_auto_detect_with_argv(): void
    {
        $this->expectOutputRegex('~auto-detected core~');

        $this->createRunCommand()->run(
            $this->stubInputWithNoPath(['--arg1', 'value']),
            $this->stubOutput(),
        );
    }

    private function createRunCommand(): RunCommand
    {
        return new RunCommand();
    }

    private function stubInputWithNoPath(array $argv = []): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturnCallback(
            static fn (string $name): string|array|null => match ($name) {
                'path' => null,
                'argv' => $argv,
                default => '',
            },
        );
        $input->method('getOption')->willReturn(false);

        return $input;
    }
}
