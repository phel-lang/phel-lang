<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Lint\Infrastructure\Command\LintCommand;
use Symfony\Component\Console\Command\LazyCommand;

final class LintCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('lint', [], 'Run the semantic linter on one or more Phel files or directories.', false, static fn(): LintCommand => new LintCommand()),
        ];
    }
}
