<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Watch\Infrastructure\Command\WatchCommand;

final class WatchCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new WatchCommand(),
        ];
    }
}
