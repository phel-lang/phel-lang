<?php

declare(strict_types=1);

namespace PhelTest\Integration\Commands;

use Phel\Commands\TestCommand;
use Phel\Commands\Utils\NamespaceExtractor;
use Phel\Compiler\EvalCompiler;
use Phel\Runtime;
use Phel\RuntimeInterface;
use PHPUnit\Framework\TestCase;

final class TestCommandTest extends TestCase
{
    public function testAllInProject(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $runtime = Runtime::initializeNew();
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

        $runtime = Runtime::initializeNew();
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

        $runtime = Runtime::initializeNew();
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
            NamespaceExtractor::create(),
            new EvalCompiler($runtime->getEnv())
        );
    }
}
