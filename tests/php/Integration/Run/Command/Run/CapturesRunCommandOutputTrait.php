<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\StackTraceOption;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;

use function ob_get_clean;
use function ob_start;

/**
 * Runs `phel run` against a fixture and hands back everything it printed.
 *
 * @psalm-require-extends AbstractTestCommand
 *
 * @phpstan-require-extends AbstractTestCommand
 */
trait CapturesRunCommandOutputTrait
{
    /**
     * @param list<string> $argv
     */
    private function captureRunOutput(string $path, array $argv = [], bool $stackTrace = false): string
    {
        ob_start();
        $this->createRunCommand()->run(
            $this->stubInput($path, $argv, $stackTrace),
            $this->stubOutput(),
        );

        return ob_get_clean() ?: '';
    }

    private function createRunCommand(): RunCommand
    {
        return new RunCommand();
    }

    /**
     * @param list<string> $argv
     */
    private function stubInput(string $path, array $argv = [], bool $stackTrace = false): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturnCallback(
            static fn(string $name): string|array => match ($name) {
                'path' => $path,
                'argv' => $argv,
                default => '',
            },
        );
        $input->method('getOption')->willReturnCallback(
            static fn(string $name): bool => $name === StackTraceOption::NAME && $stackTrace,
        );

        return $input;
    }
}
