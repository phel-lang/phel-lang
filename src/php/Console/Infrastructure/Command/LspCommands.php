<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Lsp\Infrastructure\Command\LspCommand;

final class LspCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new LspCommand(),
        ];
    }
}
