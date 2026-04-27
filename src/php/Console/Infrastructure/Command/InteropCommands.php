<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Interop\Infrastructure\Command\ExportCommand;

final class InteropCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new ExportCommand(),
        ];
    }
}
