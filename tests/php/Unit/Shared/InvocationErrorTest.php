<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\InvocationError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

final class InvocationErrorTest extends TestCase
{
    public function test_it_writes_the_message_to_stderr_and_returns_exit_2(): void
    {
        $stderr = new BufferedOutput();
        $output = new ConsoleOutput();
        $output->setErrorOutput($stderr);

        $exitCode = InvocationError::report($output, 'Unknown format: xml. Known: text, json.');

        self::assertSame(Command::INVALID, $exitCode);
        self::assertSame("Unknown format: xml. Known: text, json.\n", $stderr->fetch());
    }

    public function test_an_output_without_stderr_gets_the_message_itself(): void
    {
        $output = new BufferedOutput();

        InvocationError::report($output, 'Path not found: missing.phel');

        self::assertSame("Path not found: missing.phel\n", $output->fetch());
    }
}
