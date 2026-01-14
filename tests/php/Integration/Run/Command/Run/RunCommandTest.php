<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Phel\Run\Infrastructure\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

final class RunCommandTest extends AbstractTestCommand
{
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
            $this->stubInput(__DIR__ . '/Fixtures/test/test-script.phel'),
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
        $this->expectOutputRegex('~first:--myarg~');

        $this->createRunCommand()->run(
            new ArgvInput(['run', __DIR__ . '/Fixtures/argv-script.phel', '--', '--myarg']),
            $this->stubOutput(),
        );
    }

    public function test_program_contains_script_path(): void
    {
        $scriptPath = __DIR__ . '/Fixtures/argv-script.phel';
        $this->expectOutputRegex('~program:' . preg_quote($scriptPath, '~') . '~');

        $this->createRunCommand()->run(
            new ArgvInput(['run', $scriptPath, '--', 'arg1']),
            $this->stubOutput(),
        );
    }

    public function test_argv_does_not_contain_script_name(): void
    {
        $scriptPath = __DIR__ . '/Fixtures/argv-script.phel';
        // argv should be ["arg1" "arg2"], not contain the script path
        $this->expectOutputRegex('~argv:\["arg1" "arg2"\]~');

        $this->createRunCommand()->run(
            new ArgvInput(['run', $scriptPath, '--', 'arg1', 'arg2']),
            $this->stubOutput(),
        );
    }

    public function test_argv_first_element_is_first_user_arg(): void
    {
        $this->expectOutputRegex('~first:--verbose~');

        $this->createRunCommand()->run(
            new ArgvInput(['run', __DIR__ . '/Fixtures/argv-script.phel', '--', '--verbose', 'file.txt']),
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
