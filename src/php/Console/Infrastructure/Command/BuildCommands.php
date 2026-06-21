<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Build\Infrastructure\Command\CacheClearCommand;
use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Symfony\Component\Console\Command\LazyCommand;

final class BuildCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('build', ['b'], 'Build the current project', false, static fn(): BuildCommand => new BuildCommand()),
            new LazyCommand('cache:clear', [], 'Clear the temp and cache directories', false, static fn(): CacheClearCommand => new CacheClearCommand()),
        ];
    }
}
