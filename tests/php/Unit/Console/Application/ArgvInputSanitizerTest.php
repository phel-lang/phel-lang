<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Application;

use Phel\Console\Application\ArgvInputSanitizer;
use Phel\Run\Infrastructure\Command\RunCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

use function array_slice;

final class ArgvInputSanitizerTest extends TestCase
{
    public function test_returns_argv_unchanged_when_not_a_run_invocation(): void
    {
        $argv = ['phel', 'test', '--filter=foo'];

        self::assertSame($argv, $this->sanitizer()->sanitize($argv));
    }

    public function test_returns_argv_unchanged_when_argv_too_short(): void
    {
        $argv = ['phel'];

        self::assertSame($argv, $this->sanitizer()->sanitize($argv));
    }

    public function test_keeps_bare_run_invocation_as_is(): void
    {
        $argv = ['phel', 'run'];

        self::assertSame(['phel', 'run'], $this->sanitizer()->sanitize($argv));
    }

    public function test_collects_single_option_before_command(): void
    {
        $argv = ['phel', 'run', '-t', 'cmd'];

        self::assertSame(
            ['phel', 'run', '-t', 'cmd'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_collects_multiple_options_before_command(): void
    {
        $argv = ['phel', 'run', '-t', '--with-time', '--clear-opcache', 'cmd'];

        self::assertSame(
            ['phel', 'run', '-t', '--with-time', '--clear-opcache', 'cmd'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_only_options_without_command(): void
    {
        $argv = ['phel', 'run', '--with-time'];

        self::assertSame(
            ['phel', 'run', '--with-time'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_separates_command_args_with_double_dash(): void
    {
        $argv = ['phel', 'run', '-t', 'cmd', 'arg1', 'arg2'];

        self::assertSame(
            ['phel', 'run', '-t', 'cmd', '--', 'arg1', 'arg2'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_unknown_option_is_treated_as_command(): void
    {
        // --foo is not a known RUN_OPTION, so option collection stops and it
        // becomes the command token; the rest is forwarded after a separator.
        $argv = ['phel', 'run', '--foo', 'arg1'];

        self::assertSame(
            ['phel', 'run', '--foo', '--', 'arg1'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_command_args_following_command_without_run_options(): void
    {
        $argv = ['phel', 'run', 'cmd', 'arg1'];

        self::assertSame(
            ['phel', 'run', 'cmd', '--', 'arg1'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_collects_stack_trace_and_debug_before_the_path(): void
    {
        $argv = ['phel', 'run', '--stack-trace', '--debug', 'app.phel'];

        self::assertSame(
            ['phel', 'run', '--stack-trace', '--debug=', 'app.phel'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_keeps_the_filter_of_a_debug_option_given_with_a_value(): void
    {
        $argv = ['phel', 'run', '--debug=core', 'app.phel', 'arg1'];

        self::assertSame(
            ['phel', 'run', '--debug=core', 'app.phel', '--', 'arg1'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_collects_the_deprecation_flag_before_the_path(): void
    {
        $argv = ['phel', 'run', '--warn-deprecations', 'app.phel', 'arg1'];

        self::assertSame(
            ['phel', 'run', '--warn-deprecations', 'app.phel', '--', 'arg1'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    public function test_forwards_options_after_the_path_to_the_script(): void
    {
        $argv = ['phel', 'run', 'app.phel', '--debug', '--stack-trace', '--warn-deprecations', '-t'];

        self::assertSame(
            ['phel', 'run', 'app.phel', '--', '--debug', '--stack-trace', '--warn-deprecations', '-t'],
            $this->sanitizer()->sanitize($argv),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEveryRunOption(): iterable
    {
        foreach (new RunCommand()->getDefinition()->getOptions() as $option) {
            yield '--' . $option->getName() => ['--' . $option->getName()];

            if ($option->getShortcut() !== null) {
                yield '-' . $option->getShortcut() => ['-' . $option->getShortcut()];
            }
        }
    }

    /**
     * A new option on `RunCommand` must reach Symfony as an option, not be
     * read as the path.
     */
    #[DataProvider('provideEveryRunOption')]
    public function test_collects_every_option_the_run_command_declares(string $option): void
    {
        $result = $this->sanitizer()->sanitize(['phel', 'run', $option, 'app.phel', 'arg1']);

        self::assertSame('app.phel', $result[3] ?? null, $option);
        self::assertSame(['--', 'arg1'], array_slice($result, 4), $option);
    }

    private function sanitizer(): ArgvInputSanitizer
    {
        return new ArgvInputSanitizer(static fn(): InputDefinition => new RunCommand()->getDefinition());
    }
}
