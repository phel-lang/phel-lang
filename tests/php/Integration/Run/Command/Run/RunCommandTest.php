<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\Infrastructure\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractCommandTest;
use Symfony\Component\Console\Input\InputInterface;

final class RunCommandTest extends AbstractCommandTest
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
    public function test_run_by_namespace(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput('test\\test-script'),
            $this->stubOutput()
        );
    }

    public function test_run_by_filename(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput(__DIR__ . '/Fixtures/test-script.phel'),
            $this->stubOutput()
        );
    }

    private function createRunCommand(): RunCommand
    {
        return new RunCommand();
    }

    private function stubInput(string $path): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($path);

        return $input;
    }
}
