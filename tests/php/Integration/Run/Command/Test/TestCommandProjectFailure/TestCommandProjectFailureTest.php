<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandProjectFailure;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\Infrastructure\Command\TestCommand;
use PhelTest\Integration\Run\Command\AbstractCommandTest;
use Symfony\Component\Console\Input\InputInterface;

final class TestCommandProjectFailureTest extends AbstractCommandTest
{
    public static function setUpBeforeClass(): void
    {
        $configFn = static function (GacelaConfig $config): void {
            $config->addAppConfig('config/*.php');
        };

        Gacela::bootstrap(__DIR__, $configFn);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_all_in_failed_project(): void
    {
        $command = $this->getTestCommand();

        $this->expectOutputRegex('/E.*/');
        $this->expectOutputRegex('/.*Passed: 0.*/');
        $this->expectOutputRegex('/.*Failed: 1.*/');
        $this->expectOutputRegex('/.*Error: 0.*/');
        $this->expectOutputRegex('/.*Total: 1.*/');

        $command->run($this->stubInput([]), $this->stubOutput());
    }

    protected function stubInput(array $paths = []): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($paths);

        return $input;
    }

    private function getTestCommand(): TestCommand
    {
        return new TestCommand();
    }
}
