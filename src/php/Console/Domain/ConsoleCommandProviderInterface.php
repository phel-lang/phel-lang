<?php

declare(strict_types=1);

namespace Phel\Console\Domain;

use Symfony\Component\Console\Command\Command;

/**
 * Per-module provider of Symfony Console commands. Each module implements this
 * to expose its own commands; ConsoleProvider aggregates every implementation
 * via its COMMANDS dependency to build the full CLI.
 */
interface ConsoleCommandProviderInterface
{
    /**
     * @return list<Command>
     */
    public function commands(): array;
}
