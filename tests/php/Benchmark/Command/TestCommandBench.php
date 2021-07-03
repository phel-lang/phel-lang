<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Command;

use Phel\Command\CommandFactory;
use Phel\Command\Test\TestCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @BeforeMethods("setUp")
 */
final class TestCommandBench
{
    private TestCommand $command;

    public function setUp(): void
    {
        $this->command = (new CommandFactory())->createTestCommand();
    }

    public function bench_test_command(): void
    {
        ob_start();
        $this->command->run(
            new StringInput(__DIR__ . '/src/fixture.phel'),
            new NullOutput()
        );
        ob_clean();
    }
}
