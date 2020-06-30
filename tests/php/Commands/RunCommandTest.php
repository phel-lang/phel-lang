<?php

namespace Phel\Commands;

use Phel\Runtime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RunCommandTest extends TestCase
{
    public function testRunByNamespace()
    {
        $runtime = Runtime::initializeNew();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);

        $run = new RunCommand($runtime);

        $this->expectOutputString("hello world\n");
        $run->run(__DIR__, 'test\\test-script');
    }

    public function testRunByFilename()
    {
        $runtime = Runtime::initializeNew();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);

        $run = new RunCommand($runtime);

        $this->expectOutputString("hello world\n");
        $run->run(__DIR__, __DIR__ . '/Fixtures/test-script.phel');
    }

    public function testCannotParseFile()
    {
        $filename = __DIR__ . '/Fixtures/test-script-not-parsable.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse file: ' . $filename);

        $run = new RunCommand(Runtime::newInstance());
        $run->run(__DIR__, $filename);
    }

    public function testCannotReadFile()
    {
        $filename = __DIR__ . '/Fixtures/this-file-does-not-exist.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load namespace: ' . $filename);

        $run = new RunCommand(Runtime::newInstance());
        $run->run(__DIR__, $filename);
    }

    public function testFileWithoutNs()
    {
        $filename = __DIR__ . '/Fixtures/test-script-without-ns.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot extract namespace from file: ' . $filename);

        $run = new RunCommand(Runtime::newInstance());
        $run->run(__DIR__, $filename);
    }
}
