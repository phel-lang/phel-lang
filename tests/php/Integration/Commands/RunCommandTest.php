<?php

declare(strict_types=1);

namespace PhelTest\Integration\Commands;

use Phel\Commands\RunCommand;
use Phel\Commands\Utils\NamespaceExtractor;
use Phel\Runtime;
use Phel\RuntimeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunCommandTest extends TestCase
{
    public function testRunByNamespace(): void
    {
        $this->expectOutputString("hello world\n");

        $runtime = Runtime::initializeNew();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);
        $runCommand = $this->createRunCommand($runtime);
        $runCommand->run('test\\test-script');
    }

    public function testRunByFilename(): void
    {
        $this->expectOutputString("hello world\n");

        $runtime = Runtime::initializeNew();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);
        $runCommand = $this->createRunCommand($runtime);
        $runCommand->run(__DIR__ . '/Fixtures/test-script.phel');
    }

    public function testCannotParseFile(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-not-parsable.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse file: ' . $filename);

        $runCommand = $this->createRunCommand(Runtime::newInstance());
        $runCommand->run($filename);
    }

    public function testCannotReadFile(): void
    {
        $filename = __DIR__ . '/Fixtures/this-file-does-not-exist.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load namespace: ' . $filename);

        $runCommand = $this->createRunCommand(Runtime::newInstance());
        $runCommand->run($filename);
    }

    public function testFileWithoutNs(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-without-ns.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot extract namespace from file: ' . $filename);

        $runCommand = $this->createRunCommand(Runtime::newInstance());
        $runCommand->run($filename);
    }

    private function createRunCommand(RuntimeInterface $runtime): RunCommand
    {
        return new RunCommand(
            $runtime,
            NamespaceExtractor::create()
        );
    }
}
