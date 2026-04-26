<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Api\Infrastructure\Command\AnalyzeCommand;
use Phel\Api\Infrastructure\Command\ApiDaemonCommand;
use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Api\Infrastructure\Command\IndexCommand;
use Phel\Console\Domain\ConsoleCommandProviderInterface;

final class ApiCommands implements ConsoleCommandProviderInterface
{
    public function commands(): array
    {
        return [
            new DocCommand(),
            new AnalyzeCommand(),
            new IndexCommand(),
            new ApiDaemonCommand(),
        ];
    }
}
