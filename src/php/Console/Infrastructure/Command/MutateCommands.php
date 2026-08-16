<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Mutate\Infrastructure\Command\MutateCommand;
use Phel\Mutate\Infrastructure\Command\MutateWorkerCommand;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * @internal
 */
final class MutateCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand(MutateCommand::COMMAND_NAME, [], 'Mutation testing: mutate every defn and report the mutants the test suite does not catch.', false, static fn(): MutateCommand => new MutateCommand()),
            new LazyCommand(MutateWorkerCommand::COMMAND_NAME, [], 'Internal: mutation-testing worker. Not for direct use.', true, static fn(): MutateWorkerCommand => new MutateWorkerCommand()),
        ];
    }
}
