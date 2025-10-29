<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandProjectFailure;

use Phel\Phel;
use Phel\Run\Infrastructure\Command\TestCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\Console\Input\InputInterface;

final class TestProjectFailureTestCommand extends AbstractTestCommand
{
    public function __construct()
    {
        parent::__construct(self::class);
    }

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
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
