<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Nrepl\Infrastructure\Command\NreplCommand;
use Symfony\Component\Console\Command\LazyCommand;

final class NreplCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('nrepl', [], 'Start an nREPL server for editor tooling (bencode-over-TCP protocol).', false, static fn(): NreplCommand => new NreplCommand()),
        ];
    }
}
