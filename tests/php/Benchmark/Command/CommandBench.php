<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Command;

use Phel\Command\CommandFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @BeforeMethods("setUp")
 */
final class CommandBench
{
    private CommandFactory $commandFactory;

    public function setUp(): void
    {
        $this->commandFactory = new CommandFactory();
    }

    public function bench_run_command(): void
    {
        $this->runCommand($this->commandFactory->createRunCommand());
    }

    public function bench_test_command(): void
    {
        $this->runCommand($this->commandFactory->createTestCommand());
    }

    public function bench_format_command(): void
    {
        $this->runCommand($this->commandFactory->createFormatCommand());
    }

    private function runCommand(Command $command): void
    {
        ob_start();
        $command->run(
            new StringInput(__DIR__ . '/src/fixture.phel'),
            new NullOutput()
        );
        ob_clean();
    }
}
