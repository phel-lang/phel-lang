<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Phel\Run\Infrastructure\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
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
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/argv-script.phel',
            ['--myarg'],
        );

        self::assertMatchesRegularExpression('~first:--myarg~', $output);
    }

    public function test_program_contains_script_path(): void
    {
        $scriptPath = __DIR__ . '/Fixtures/argv-script.phel';

        $output = $this->captureRunOutput($scriptPath, ['arg1']);

        self::assertMatchesRegularExpression(
            '~program:' . preg_quote($scriptPath, '~') . '~',
            $output,
        );
    }

    public function test_argv_does_not_contain_script_name(): void
    {
        $scriptPath = __DIR__ . '/Fixtures/argv-script.phel';

        $output = $this->captureRunOutput($scriptPath, ['arg1', 'arg2']);

        // argv should contain only user args, not the script path
        self::assertStringContainsString('count:2', $output);
        self::assertStringContainsString('first:arg1', $output);
        self::assertStringContainsString('second:arg2', $output);
    }

    public function test_argv_first_element_is_first_user_arg(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/argv-script.phel',
            ['--verbose', 'file.txt'],
        );

        self::assertMatchesRegularExpression('~first:--verbose~', $output);
    }

    private function captureRunOutput(string $path, array $argv = []): string
    {
        ob_start();
        $this->createRunCommand()->run(
            $this->stubInput($path, $argv),
            $this->stubOutput(),
        );

        return ob_get_clean() ?: '';
    }

    private function createRunCommand(): RunCommand
    {
        return new RunCommand();
    }

    private function stubInput(string $path, array $argv = []): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturnCallback(
            static fn (string $name): string|array => match ($name) {
                'path' => $path,
                'argv' => $argv,
                default => '',
            },
        );

        return $input;
    }
}
