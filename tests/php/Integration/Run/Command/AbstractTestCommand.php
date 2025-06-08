<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command;

use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractTestCommand extends TestCase
{
    protected function setUp(): void
    {
        GlobalEnvironmentSingleton::reset();
    }

    protected function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(static fn (string $str): int => print $str . PHP_EOL);
        $output->method('write')
            ->willReturnCallback(static fn (string $str): int => print $str);

        return $output;
    }
}
