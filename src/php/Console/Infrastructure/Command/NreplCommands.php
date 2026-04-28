<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Nrepl\Infrastructure\Command\NreplCommand;

final class NreplCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new NreplCommand(),
        ];
    }
}
