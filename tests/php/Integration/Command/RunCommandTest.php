<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command;

use Phel\Command\CommandFactory;
use Phel\Command\CommandFactoryInterface;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\GlobalEnvironment;
use Phel\Runtime\RuntimeFactory;
use Phel\Runtime\RuntimeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunCommandTest extends TestCase
{
    public function testRunByNamespace(): void
    {
        $this->expectOutputString("hello world\n");

        $runtime = $this->createRuntime();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);

        $this->createCommandFactory()
            ->createRunCommand($runtime)
            ->run('test\\test-script');
    }

    public function testRunByFilename(): void
    {
        $this->expectOutputString("hello world\n");

        $runtime = $this->createRuntime();
        $runtime->addPath('test\\', [__DIR__ . '/Fixtures']);

        $this->createCommandFactory()
            ->createRunCommand($runtime)
            ->run(__DIR__ . '/Fixtures/test-script.phel');
    }

    public function testCannotParseFile(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-not-parsable.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse file: ' . $filename);

        $this->createCommandFactory()
            ->createRunCommand($this->createRuntime())
            ->run($filename);
    }

    public function testCannotReadFile(): void
    {
        $filename = __DIR__ . '/Fixtures/this-file-does-not-exist.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot load namespace: ' . $filename);

        $this->createCommandFactory()
            ->createRunCommand($this->createRuntime())
            ->run($filename);
    }

    public function testFileWithoutNs(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-without-ns.phel';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot extract namespace from file: ' . $filename);

        $this->createCommandFactory()
            ->createRunCommand($this->createRuntime())
            ->run($filename);
    }

    private function createRuntime(): RuntimeInterface
    {
        return RuntimeFactory::initializeNew(new GlobalEnvironment());
    }

    private function createCommandFactory(): CommandFactoryInterface
    {
        return new CommandFactory(__DIR__, new CompilerFactory());
    }
}
