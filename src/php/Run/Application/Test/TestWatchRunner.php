<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\Facade\CommandFacadeInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Resolves which directories `phel test --watch` observes (the project's
 * source and test directories) and hands them to the watch loop.
 */
final readonly class TestWatchRunner
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private TestWatchLoop $loop,
    ) {}

    /**
     * @param callable():int $runTests
     */
    public function run(callable $runTests, OutputInterface $output): int
    {
        $directories = [
            ...$this->commandFacade->getProjectSourceDirectories(),
            ...$this->commandFacade->getTestDirectories(),
        ];

        return $this->loop->run($directories, $runTests, $output);
    }
}
