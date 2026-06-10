<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Run\Infrastructure\Command\AgentInstallCommand;
use Phel\Run\Infrastructure\Command\CompileCommand;
use Phel\Run\Infrastructure\Command\ConfigCommand;
use Phel\Run\Infrastructure\Command\DoctorCommand;
use Phel\Run\Infrastructure\Command\EvalCommand;
use Phel\Run\Infrastructure\Command\InitCommand;
use Phel\Run\Infrastructure\Command\NsCommand;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;
use Phel\Run\Infrastructure\Command\TestWorkerCommand;

final class RunCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new InitCommand(),
            new AgentInstallCommand(),
            new NsCommand(),
            new ReplCommand(),
            new EvalCommand(),
            new CompileCommand(),
            new RunCommand(),
            new TestCommand(),
            new TestWorkerCommand(),
            new DoctorCommand(),
            new ConfigCommand(),
        ];
    }
}
