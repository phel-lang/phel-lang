<?php

declare(strict_types=1);

namespace PhelTest\Commands;

use Phel\Commands\TestCommand;
use Phel\Runtime;
use PHPUnit\Framework\TestCase;

final class TestCommandTest extends TestCase
{
    public function testAllInProject(): void
    {
        $runtime = Runtime::initializeNew();
        $runtime->addPath('test-cmd-project-success\\', [__DIR__ . '/Fixtures/test-cmd-project-success/tests']);
        $runtime->addPath('phel\\', [__DIR__ . '/../../../src/phel']);
        $runtime->loadNs('phel\core');

        $testCmd = new TestCommand($runtime);

        $this->expectOutputString("..\n\n\n\nPassed: 2\nFailed: 0\nError: 0\nTotal: 2\n");
        $this->assertTrue(
            $testCmd->run(__DIR__ . '/Fixtures/test-cmd-project-success/', [])
        );
    }

    public function testOneFileInProject(): void
    {
        $runtime = Runtime::initializeNew();
        $runtime->addPath('test-cmd-project-success\\', [__DIR__ . '/Fixtures/test-cmd-project-success/tests']);
        $runtime->addPath('phel\\', [__DIR__ . '/../../../src/phel']);
        $runtime->loadNs('phel\core');

        $testCmd = new TestCommand($runtime);

        $this->expectOutputString(".\n\n\n\nPassed: 1\nFailed: 0\nError: 0\nTotal: 1\n");
        $this->assertTrue(
            $testCmd->run(
                __DIR__ . '/Fixtures/test-cmd-project-success/',
                [__DIR__ . '/Fixtures/test-cmd-project-success/tests/test1.phel']
            )
        );
    }

    public function testAllInFailedProject(): void
    {
        $runtime = Runtime::initializeNew();
        $runtime->addPath('test-cmd-project-failure\\', [__DIR__ . '/Fixtures/test-cmd-project-failure/tests']);
        $runtime->addPath('phel\\', [__DIR__ . '/../../../src/phel']);
        $runtime->loadNs('phel\core');

        $testCmd = new TestCommand($runtime);

        $this->expectOutputRegex('/.*Failed\\: 1.*/');
        $this->assertFalse(
            $testCmd->run(__DIR__ . '/Fixtures/test-cmd-project-failure/', [])
        );
    }
}
