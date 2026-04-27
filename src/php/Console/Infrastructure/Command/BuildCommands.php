<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Build\Infrastructure\Command\CacheClearCommand;
use Phel\Console\Domain\ConsoleCommandProviderInterface;

final class BuildCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new BuildCommand(),
            new CacheClearCommand(),
        ];
    }
}
