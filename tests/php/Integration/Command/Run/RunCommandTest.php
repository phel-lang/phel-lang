<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Run;

use Gacela\Framework\Config;
use Phel\Command\CommandFactory;
use Phel\Runtime\RuntimeSingleton;
use PHPUnit\Framework\TestCase;

final class RunCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function setUp(): void
    {
        RuntimeSingleton::reset();
    }

    public function testRunByNamespace(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createCommandFactory()
            ->createRunCommand()
            ->addRuntimePath('test\\', [__DIR__ . '/Fixtures'])
            ->run('test\\test-script');
    }

    public function testRunByFilename(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createCommandFactory()
            ->createRunCommand()
            ->addRuntimePath('test\\', [__DIR__ . '/Fixtures'])
            ->run(__DIR__ . '/Fixtures/test-script.phel');
    }

    public function testCannotParseFile(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-not-parsable.phel';
        $this->expectOutputRegex(sprintf('~Cannot parse file: %s~', $filename));

        $this->createCommandFactory()
            ->createRunCommand()
            ->run($filename);
    }

    public function testCannotReadFile(): void
    {
        $filename = __DIR__ . '/Fixtures/this-file-does-not-exist.phel';
        $this->expectOutputRegex(sprintf('~Cannot load namespace: %s~', $filename));

        $this->createCommandFactory()
            ->createRunCommand()
            ->run($filename);
    }

    public function testFileWithoutNs(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-without-ns.phel';
        $this->expectOutputRegex(sprintf('~Cannot extract namespace from file: %s~', $filename));

        $this->createCommandFactory()
            ->createRunCommand()
            ->run($filename);
    }

    private function createCommandFactory(): CommandFactory
    {
        return new CommandFactory();
    }
}
