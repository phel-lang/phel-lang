<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Profile\Infrastructure\Command\ProfileCommand;

final class ProfileCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new ProfileCommand(),
        ];
    }
}
