<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Lint\Infrastructure\Command\LintCommand;

final class LintCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new LintCommand(),
        ];
    }
}
