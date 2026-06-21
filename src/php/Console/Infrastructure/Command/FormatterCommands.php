<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Symfony\Component\Console\Command\LazyCommand;

final class FormatterCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('format', ['fmt'], 'Formats the given files', false, static fn(): FormatCommand => new FormatCommand()),
        ];
    }
}
