<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Balance\Infrastructure\Command\BalanceCommand;
use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * @internal
 */
final class BalanceCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('balance', [], 'Report unbalanced parentheses, brackets and braces in Phel files; repair them with --fix.', false, static fn(): BalanceCommand => new BalanceCommand()),
        ];
    }
}
