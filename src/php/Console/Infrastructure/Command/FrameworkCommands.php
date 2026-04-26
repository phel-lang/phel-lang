<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Console\Infrastructure\Command\DebugContainerCommand;
use Gacela\Console\Infrastructure\Command\DebugDependenciesCommand;
use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Phel\Console\Domain\ConsoleCommandProviderInterface;

final class FrameworkCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new CacheWarmCommand(),
            new DebugContainerCommand(),
            new DebugDependenciesCommand(),
            new DebugModulesCommand(),
            new ListModulesCommand(),
            new ProfileReportCommand(),
            new ValidateConfigCommand(),
        ];
    }
}
