<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\StandardError;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

use function ob_get_level;
use function ob_start;

final class StandardErrorTest extends TestCase
{
    public function test_it_names_the_console_error_output(): void
    {
        $stderr = new BufferedOutput();
        $output = new ConsoleOutput();
        $output->setErrorOutput($stderr);

        self::assertSame($stderr, StandardError::of($output));
    }

    public function test_an_output_without_stderr_is_its_own_error_output(): void
    {
        $output = new BufferedOutput();

        self::assertSame($output, StandardError::of($output));
    }

    public function test_it_returns_what_the_function_returns(): void
    {
        self::assertSame(42, StandardError::redirectEcho(static fn(): int => 42));
    }

    public function test_a_throw_that_leaves_a_buffer_open_restores_the_buffer_level(): void
    {
        $level = ob_get_level();

        try {
            StandardError::redirectEcho(static function (): never {
                ob_start();
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        self::assertSame($level, ob_get_level());
    }
}
