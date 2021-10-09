<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Gacela\Framework\Gacela;
use Phel\Run\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractCommandTest;
use Symfony\Component\Console\Input\InputInterface;

final class RunCommandTest extends AbstractCommandTest
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_run_by_namespace(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->getRunCommand()->run(
            $this->stubInput('test\\test-script'),
            $this->stubOutput()
        );
    }

    public function test_run_by_filename(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->getRunCommand()->run(
            $this->stubInput(__DIR__ . '/Fixtures/test-script.phel'),
            $this->stubOutput()
        );
    }

    private function getRunCommand(): RunCommand
    {
        return $this->createRunFacade()->getRunCommand();
    }

    private function stubInput(string $path): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($path);

        return $input;
    }
}
