<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Interop\Infrastructure\Command\ExportCommand;
use Symfony\Component\Console\Command\LazyCommand;

final class InteropCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('export', [], 'Export all definitions with the meta data `{:export true}` as PHP classes', false, static fn(): ExportCommand => new ExportCommand()),
        ];
    }
}
