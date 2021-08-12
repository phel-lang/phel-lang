<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command;

use Phel\Command\CommandFacade;
use Phel\Runtime\RuntimeSingleton;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommandTest extends TestCase
{
    protected function setUp(): void
    {
        RuntimeSingleton::reset();
    }

    protected function createCommandFacade(): CommandFacade
    {
        return new CommandFacade();
    }

    protected function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(fn (string $str) => print $str . PHP_EOL);

        return $output;
    }
}
