<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Profile\Infrastructure\Command\ProfileCommand;
use Symfony\Component\Console\Command\LazyCommand;

final class ProfileCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('profile', [], 'Profile a Phel script: per-fn call counts and timings, plus compile-time phase costs.', false, static fn(): ProfileCommand => new ProfileCommand()),
        ];
    }
}
