<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton;
use Phel\Run\RunFacade;
use Phel\Run\RunFacadeInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommandTest extends TestCase
{
    protected function setUp(): void
    {
        GlobalEnvironmentSingleton::reset();
    }

    protected function createRunFacade(): RunFacadeInterface
    {
        return new RunFacade();
    }

    protected function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(fn (string $str) => print $str . PHP_EOL);

        return $output;
    }
}
