<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Phel\Run\Infrastructure\Command\TestLoadingFeedback;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TestLoadingFeedbackTest extends TestCase
{
    public function test_discovering_announces_immediately(): void
    {
        $output = new BufferedOutput();

        new TestLoadingFeedback($output)->discovering();

        self::assertStringContainsString('Discovering tests...', $output->fetch());
    }

    public function test_non_decorated_output_gets_one_static_loading_line(): void
    {
        $output = new BufferedOutput(decorated: false);
        $feedback = new TestLoadingFeedback($output);

        $feedback->startLoading(3);
        $feedback->advance('app.a');
        $feedback->advance('app.b');
        $feedback->finishLoading();

        self::assertSame("Loading 3 namespace(s)...\n", $output->fetch());
    }

    public function test_decorated_output_rewrites_the_loading_line_in_place(): void
    {
        $output = new BufferedOutput(decorated: true);
        $feedback = new TestLoadingFeedback($output);

        $feedback->startLoading(2);
        $feedback->advance('app.a');
        $feedback->advance('app.b');
        $feedback->finishLoading();

        $rendered = $output->fetch();
        self::assertStringContainsString('Loading namespaces 1/2 (app.a)', $rendered);
        self::assertStringContainsString('Loading namespaces 2/2 (app.b)', $rendered);
        self::assertStringEndsWith("\r\033[2K", $rendered, 'the progress line is cleared at the end');
    }

    public function test_from_output_uses_the_error_stream_of_a_console_output(): void
    {
        $stderr = new BufferedOutput();
        $console = $this->createStub(ConsoleOutputInterface::class);
        $console->method('getErrorOutput')->willReturn($stderr);

        TestLoadingFeedback::fromOutput($console)->discovering();

        self::assertStringContainsString('Discovering tests...', $stderr->fetch());
    }

    public function test_from_output_stays_silent_without_an_error_stream(): void
    {
        $output = new BufferedOutput();

        TestLoadingFeedback::fromOutput($output)->discovering();

        self::assertSame('', $output->fetch(), 'progress must never leak into a plain stdout stream');
    }

    public function test_quiet_verbosity_suppresses_all_feedback(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_QUIET);
        $feedback = new TestLoadingFeedback($output);

        $feedback->discovering();
        $feedback->startLoading(5);

        self::assertSame('', $output->fetch());
    }
}
