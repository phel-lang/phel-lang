<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Test;

use Gacela\Framework\Gacela;
use Phel\Command\Test\TestCommand;
use PhelTest\Integration\Command\AbstractCommandTest;
use Symfony\Component\Console\Input\InputInterface;

final class TestCommandTest extends AbstractCommandTest
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
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $command = $this->getTestCommand()
            ->addRuntimePath('test-cmd-project-success\\', [$currentDir]);

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
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-success/';

        $command = $this->getTestCommand()
            ->addRuntimePath('test-cmd-project-success\\', [$currentDir]);

        $this->expectOutputRegex('/\..*/');
        $this->expectOutputRegex('/.*Passed: 1.*/');
        $this->expectOutputRegex('/.*Failed: 0.*/');
        $this->expectOutputRegex('/.*Error: 0.*/');
        $this->expectOutputRegex('/.*Total: 1.*/');

        $command->run(
            $this->stubInput([$currentDir . '/test1.phel']),
            $this->stubOutput()
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_all_in_failed_project(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-project-failure/';

        $command = $this->getTestCommand()
            ->addRuntimePath('test-cmd-project-failure\\', [$currentDir]);

        $this->expectOutputRegex('/E.*/');
        $this->expectOutputRegex('/.*Passed: 0.*/');
        $this->expectOutputRegex('/.*Failed: 1.*/');
        $this->expectOutputRegex('/.*Error: 0.*/');
        $this->expectOutputRegex('/.*Total: 1.*/');

        $command->run($this->stubInput([]), $this->stubOutput());
    }

    private function getTestCommand(): TestCommand
    {
        return $this->createCommandFacade()->getTestCommand();
    }

    private function stubInput(array $paths): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($paths);

        return $input;
    }
}
