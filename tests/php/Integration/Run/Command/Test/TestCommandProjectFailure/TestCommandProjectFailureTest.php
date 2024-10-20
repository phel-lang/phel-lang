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
        Gacela::bootstrap(__DIR__, GacelaConfig::defaultPhpConfig());
    }

    public function test_all_in_failed_project(): void
    {
        $command = new TestCommand();

        $this->expectOutputRegex('/E.*/');
        $this->expectOutputRegex('/.*Passed: 0.*/');
        $this->expectOutputRegex('/.*Failed: 1.*/');
        $this->expectOutputRegex('/.*Error: 0.*/');
        $this->expectOutputRegex('/.*Total: 1.*/');

        $command->run($this->stubInput([]), $this->stubOutput());
    }

    private function stubInput(array $paths = []): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($paths);

        return $input;
    }
}
