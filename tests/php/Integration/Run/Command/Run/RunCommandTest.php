<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Override;
use Phel\Phel;
use Phel\Run\Infrastructure\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

final class RunCommandTest extends AbstractTestCommand
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp(); // This clears GlobalEnvironmentSingleton
        Phel::bootstrap(__DIR__); // Re-bootstrap after clearing
    }

    public function test_file_not_found(): void
    {
        $this->expectOutputRegex('~No rendered output after running namespace: "non-existing-file.phel"~');

        $this->createRunCommand()->run(
            $this->stubInput('non-existing-file.phel'),
            $this->stubOutput(),
        );
    }

    public function test_run_by_namespace(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput('test\\test-script'),
            $this->stubOutput(),
        );
    }

    public function test_run_by_filename(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput(__DIR__ . '/Fixtures/test-script.phel'),
            $this->stubOutput(),
        );
    }

    public function test_run_by_filename_outside_config(): void
    {
        $tmpFile = __DIR__ . '/outside-script.phel';
        file_put_contents($tmpFile, "(ns outside\script)\n(php/print \"hello world\\n\")");

        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput($tmpFile),
            $this->stubOutput(),
        );

        unlink($tmpFile);
    }

    public function test_pass_flag_arguments_to_script(): void
    {
        $this->expectOutputRegex('~--myarg~');

        $this->createRunCommand()->run(
            new ArgvInput([__DIR__ . '/Fixtures/argv-script.phel', '--', '--myarg']),
            $this->stubOutput(),
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
