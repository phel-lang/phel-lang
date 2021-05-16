<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Run;

use Gacela\Framework\Config;
use Phel\Command\CommandFactory;
use Phel\Runtime\RuntimeSingleton;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->run(
                $this->stubInput('test\\test-script'),
                $this->createStub(OutputInterface::class)
            );
    }

    public function testRunByFilename(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createCommandFactory()
            ->createRunCommand()
            ->addRuntimePath('test\\', [__DIR__ . '/Fixtures'])
            ->run(
                $this->stubInput(__DIR__ . '/Fixtures/test-script.phel'),
                $this->createStub(OutputInterface::class)
            );
    }

    public function testCannotParseFile(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-not-parsable.phel';
        $this->expectOutputRegex(sprintf('~Cannot parse file: %s~', $filename));

        $this->createCommandFactory()
            ->createRunCommand()
            ->run(
                $this->stubInput($filename),
                $this->createStub(OutputInterface::class)
            );
    }

    public function testCannotReadFile(): void
    {
        $filename = __DIR__ . '/Fixtures/this-file-does-not-exist.phel';
        $this->expectOutputRegex(sprintf('~Cannot load namespace: %s~', $filename));

        $this->createCommandFactory()
            ->createRunCommand()
            ->run(
                $this->stubInput($filename),
                $this->createStub(OutputInterface::class)
            );
    }

    public function testFileWithoutNs(): void
    {
        $filename = __DIR__ . '/Fixtures/test-script-without-ns.phel';
        $this->expectOutputRegex(sprintf('~Cannot extract namespace from file: %s~', $filename));

        $this->createCommandFactory()
            ->createRunCommand()
            ->run(
                $this->stubInput($filename),
                $this->createStub(OutputInterface::class)
            );
    }

    private function createCommandFactory(): CommandFactory
    {
        return new CommandFactory();
    }

    private function stubInput(string $path): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($path);

        return $input;
    }
}
