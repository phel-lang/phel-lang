<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Test;

use Gacela\Config;
use Phel\Command\CommandFactory;
use Phel\Runtime\RuntimeSingleton;
use PHPUnit\Framework\TestCase;

final class TestCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Config::$applicationRootDir = __DIR__;
        Config::init();
    }

    public function setUp(): void
    {
        RuntimeSingleton::reset();
    }

    public function testAllInProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $testCommand = $this
            ->createCommandFactory()
            ->createTestCommand()
            ->addRuntimePath('test-cmd-project-success\\', [$currentDir]);

        $this->expectOutputString("..\n\n\n\nPassed: 2\nFailed: 0\nError: 0\nTotal: 2\n");
        self::assertTrue($testCommand->run([]));
    }

    public function testOneFileInProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $testCommand = $this
            ->createCommandFactory()
            ->createTestCommand()
            ->addRuntimePath('test-cmd-project-success\\', [$currentDir]);

        $this->expectOutputString(".\n\n\n\nPassed: 1\nFailed: 0\nError: 0\nTotal: 1\n");
        self::assertTrue($testCommand->run([$currentDir . '/test1.phel']));
    }

    public function testAllInFailedProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-failure/';

        $testCommand = $this
            ->createCommandFactory()
            ->createTestCommand()
            ->addRuntimePath('test-cmd-project-failure\\', [$currentDir]);

        $this->expectOutputRegex('/.*Failed\\: 1.*/');
        self::assertFalse($testCommand->run([]));
    }

    private function createCommandFactory(): CommandFactory
    {
        return new CommandFactory();
    }
}
