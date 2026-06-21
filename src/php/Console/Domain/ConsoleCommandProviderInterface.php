<?php

declare(strict_types=1);

namespace Phel\Console\Domain;

use Symfony\Component\Console\Command\LazyCommand;

/**
 * Per-module provider of Symfony Console commands. Each module exposes its
 * commands as lazily-instantiated {@see LazyCommand} wrappers carrying the
 * name/aliases/description/hidden metadata up front, so the CLI can render
 * list/help and resolve aliases without constructing every command;
 * ConsoleProvider aggregates every implementation into the command loader.
 */
interface ConsoleCommandProviderInterface
{
    /**
     * @return list<LazyCommand>
     */
    public function lazyCommands(): array;
}
