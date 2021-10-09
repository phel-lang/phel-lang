<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandProjectSuccess;

use Gacela\Framework\Gacela;
use Phel\Run\Command\TestCommand;
use PhelTest\Integration\Run\Command\AbstractCommandTest;
use Symfony\Component\Console\Input\InputInterface;

final class TestCommandProjectSuccessTest extends AbstractCommandTest
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_all_in_project(): void
    {
        $command = $this->getTestCommand();

        $this->expectOutputRegex('/\.\..*/');
        $this->expectOutputRegex('/.*Passed: 2.*/');
        $this->expectOutputRegex('/.*Failed: 0.*/');
        $this->expectOutputRegex('/.*Error: 0.*/');
        $this->expectOutputRegex('/.*Total: 2.*/');

        $command->run($this->stubInput([]), $this->stubOutput());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_one_file_in_project(): void
    {
        $command = $this->getTestCommand();

        $this->expectOutputRegex('/\..*/');
        $this->expectOutputRegex('/.*Passed: 1.*/');
        $this->expectOutputRegex('/.*Failed: 0.*/');
        $this->expectOutputRegex('/.*Error: 0.*/');
        $this->expectOutputRegex('/.*Total: 1.*/');

        $command->run(
            $this->stubInput([__DIR__ . '/Fixtures/test1.phel']),
            $this->stubOutput()
        );
    }

    private function getTestCommand(): TestCommand
    {
        return $this->createRunFacade()->getTestCommand();
    }

    protected function stubInput(array $paths = []): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($paths);

        return $input;
    }
}
