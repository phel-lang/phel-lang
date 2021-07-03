<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Command;

use Phel\Command\CommandFactory;
use Phel\Command\Run\RunCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @BeforeMethods("setUp")
 */
final class RunCommandBench
{
    private RunCommand $command;

    public function setUp(): void
    {
        $this->command = (new CommandFactory())->createRunCommand();
    }

    public function bench_run_command(): void
    {
        ob_start();
        $this->command->run(
            new StringInput(__DIR__ . '/src/fixture.phel'),
            new NullOutput()
        );
        ob_clean();
    }
}
