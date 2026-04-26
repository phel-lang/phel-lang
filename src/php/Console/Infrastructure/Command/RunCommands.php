<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Run\Infrastructure\Command\AgentInstallCommand;
use Phel\Run\Infrastructure\Command\DoctorCommand;
use Phel\Run\Infrastructure\Command\EvalCommand;
use Phel\Run\Infrastructure\Command\InitCommand;
use Phel\Run\Infrastructure\Command\NsCommand;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;

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
            new RunCommand(),
            new TestCommand(),
            new DoctorCommand(),
        ];
    }
}
