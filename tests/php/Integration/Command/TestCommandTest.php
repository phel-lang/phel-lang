<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command;

use Phel\Command\CommandFactory;
use Phel\Command\TestCommand;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\GlobalEnvironment;
use Phel\Runtime;
use Phel\RuntimeInterface;
use PHPUnit\Framework\TestCase;

final class TestCommandTest extends TestCase
{
    private GlobalEnvironment $globalEnv;
    private CompilerFactory $compilerFactory;
    private CommandFactory $commandFactory;

    public function setUp(): void
    {
        $this->globalEnv = new GlobalEnvironment();
        $this->compilerFactory = new CompilerFactory();
        $this->commandFactory = new CommandFactory(__DIR__, $this->compilerFactory);
    }

    public function testAllInProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $runtime = $this->createRuntime();
        $runtime->addPath('test-cmd-project-success\\', [$currentDir . 'tests']);
        $runtime->addPath('phel\\', [__DIR__ . '/../../../src/phel']);
        $runtime->loadNs('phel\core');

        $testCommand = $this->createTestCommand($currentDir, $runtime);

        $this->expectOutputString("..\n\n\n\nPassed: 2\nFailed: 0\nError: 0\nTotal: 2\n");
        self::assertTrue($testCommand->run([]));
    }

    public function testOneFileInProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $runtime = $this->createRuntime();
        $runtime->addPath('test-cmd-project-success\\', [$currentDir . 'tests']);
        $runtime->addPath('phel\\', [__DIR__ . '/../../../src/phel']);
        $runtime->loadNs('phel\core');

        $testCommand = $this->createTestCommand($currentDir, $runtime);
        $this->expectOutputString(".\n\n\n\nPassed: 1\nFailed: 0\nError: 0\nTotal: 1\n");
        self::assertTrue($testCommand->run([$currentDir . 'tests/test1.phel']));
    }

    public function testAllInFailedProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-failure/';

        $runtime = $this->createRuntime();
        $runtime->addPath('test-cmd-project-failure\\', [$currentDir . 'tests']);
        $runtime->addPath('phel\\', [__DIR__ . '/../../../src/phel']);
        $runtime->loadNs('phel\core');

        $testCommand = $this->createTestCommand($currentDir, $runtime);
        $this->expectOutputRegex('/.*Failed\\: 1.*/');
        self::assertFalse($testCommand->run([]));
    }

    private function createTestCommand(string $currentDir, RuntimeInterface $runtime): TestCommand
    {
        return new TestCommand(
            $currentDir,
            $runtime,
            $this->commandFactory->createNamespaceExtractor($runtime->getEnv()),
            $this->compilerFactory->createEvalCompiler($runtime->getEnv())
        );
    }

    private function createRuntime(): Runtime
    {
        return Runtime::initializeNew($this->globalEnv);
    }
}
