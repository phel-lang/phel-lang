<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Formatter\Infrastructure\Command\FormatCommand;

final class FormatterCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new FormatCommand(),
        ];
    }
}
