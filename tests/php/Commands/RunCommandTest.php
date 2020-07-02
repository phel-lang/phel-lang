<?php

declare(strict_types=1);

namespace PhelTest\Commands;

use Phel\Commands\RunCommand;
use Phel\Runtime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunCommandTest extends TestCase
{
    public function testRunByNamespace(): void
    {
        $runtime = Runtime::initializeNew();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);

        $run = new RunCommand($runtime);

        $this->expectOutputString("hello world\n");
        $run->run(__DIR__, 'test\\test-script');
    }

    public function testRunByFilename(): void
    {
        $runtime = Runtime::initializeNew();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);

        $run = new RunCommand($runtime);

        $this->expectOutputString("hello world\n");
        $run->run(__DIR__, __DIR__ . '/Fixtures/test-script.phel');
    }

    public function testCannotParseFile(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-not-parsable.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse file: ' . $filename);

        $run = new RunCommand(Runtime::newInstance());
        $run->run(__DIR__, $filename);
    }

    public function testCannotReadFile(): void
    {
        $filename = __DIR__ . '/Fixtures/this-file-does-not-exist.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load namespace: ' . $filename);

        $run = new RunCommand(Runtime::newInstance());
        $run->run(__DIR__, $filename);
    }

    public function testFileWithoutNs(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-without-ns.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot extract namespace from file: ' . $filename);

        $run = new RunCommand(Runtime::newInstance());
        $run->run(__DIR__, $filename);
    }
}
