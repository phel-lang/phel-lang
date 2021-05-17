<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Test;

use Gacela\Framework\Config;
use Phel\Command\CommandFactory;
use Phel\Runtime\RuntimeSingleton;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TestCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function setUp(): void
    {
        RuntimeSingleton::reset();
    }

    public function testAllInProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $command = $this
            ->createCommandFactory()
            ->createTestCommand()
            ->addRuntimePath('test-cmd-project-success\\', [$currentDir]);

        $this->expectOutputString("..\n\n\n\nPassed: 2\nFailed: 0\nError: 0\nTotal: 2\n");

        $command->run(
            $this->stubInput([]),
            $this->createStub(OutputInterface::class)
        );
    }

    public function testOneFileInProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $command = $this
            ->createCommandFactory()
            ->createTestCommand()
            ->addRuntimePath('test-cmd-project-success\\', [$currentDir]);

        $this->expectOutputString(".\n\n\n\nPassed: 1\nFailed: 0\nError: 0\nTotal: 1\n");

        $command->run(
            $this->stubInput([$currentDir . '/test1.phel']),
            $this->createStub(OutputInterface::class)
        );
    }

    public function testAllInFailedProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-failure/';

        $command = $this
            ->createCommandFactory()
            ->createTestCommand()
            ->addRuntimePath('test-cmd-project-failure\\', [$currentDir]);

        $this->expectOutputRegex('/.*Failed\\: 1.*/');

        $command->run(
            $this->stubInput([]),
            $this->createStub(OutputInterface::class)
        );
    }

    private function createCommandFactory(): CommandFactory
    {
        return new CommandFactory();
    }

    private function stubInput(array $paths): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($paths);

        return $input;
    }
}
