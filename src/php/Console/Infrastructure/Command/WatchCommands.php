<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Watch\Infrastructure\Command\WatchCommand;
use Symfony\Component\Console\Command\LazyCommand;

final class WatchCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('watch', [], 'Watch Phel files and reload namespaces on change.', false, static fn(): WatchCommand => new WatchCommand()),
        ];
    }
}
