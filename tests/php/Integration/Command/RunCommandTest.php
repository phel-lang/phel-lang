<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command;

use Phel\Command\CommandFactory;
use Phel\Command\RunCommand;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\GlobalEnvironment;
use Phel\Runtime;
use Phel\RuntimeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunCommandTest extends TestCase
{
    private CommandFactory $commandFactory;

    public function setUp(): void
    {
        $compilerFactory = new CompilerFactory();
        $this->commandFactory = new CommandFactory(__DIR__, $compilerFactory);
    }

    public function testRunByNamespace(): void
    {
        $this->expectOutputString("hello world\n");

        $runtime = $this->createRuntime();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);
        $runCommand = $this->createRunCommand($runtime);
        $runCommand->run('test\\test-script');
    }

    public function testRunByFilename(): void
    {
        $this->expectOutputString("hello world\n");

        $runtime = $this->createRuntime();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);
        $runCommand = $this->createRunCommand($runtime);
        $runCommand->run(__DIR__ . '/Fixtures/test-script.phel');
    }

    public function testCannotParseFile(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-not-parsable.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse file: ' . $filename);

        $runtime = $this->createRuntime();
        $runCommand = $this->createRunCommand($runtime);
        $runCommand->run($filename);
    }

    public function testCannotReadFile(): void
    {
        $filename = __DIR__ . '/Fixtures/this-file-does-not-exist.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load namespace: ' . $filename);

        $runtime = $this->createRuntime();
        $runCommand = $this->createRunCommand($runtime);
        $runCommand->run($filename);
    }

    public function testFileWithoutNs(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-without-ns.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot extract namespace from file: ' . $filename);

        $runtime = $this->createRuntime();
        $runCommand = $this->createRunCommand($runtime);
        $runCommand->run($filename);
    }

    private function createRunCommand(RuntimeInterface $runtime): RunCommand
    {
        return new RunCommand(
            $runtime,
            $this->commandFactory->createNamespaceExtractor($runtime->getEnv())
        );
    }

    private function createRuntime(): Runtime
    {
        return Runtime::initializeNew(new GlobalEnvironment());
    }
}
