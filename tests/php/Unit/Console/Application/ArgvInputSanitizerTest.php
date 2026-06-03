<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Application;

use Phel\Console\Application\ArgvInputSanitizer;
use PHPUnit\Framework\TestCase;

final class ArgvInputSanitizerTest extends TestCase
{
    public function test_returns_argv_unchanged_when_not_a_run_invocation(): void
    {
        $argv = ['phel', 'test', '--filter=foo'];

        self::assertSame($argv, new ArgvInputSanitizer()->sanitize($argv));
    }

    public function test_returns_argv_unchanged_when_argv_too_short(): void
    {
        $argv = ['phel'];

        self::assertSame($argv, new ArgvInputSanitizer()->sanitize($argv));
    }

    public function test_keeps_bare_run_invocation_as_is(): void
    {
        $argv = ['phel', 'run'];

        self::assertSame(['phel', 'run'], new ArgvInputSanitizer()->sanitize($argv));
    }

    public function test_collects_single_option_before_command(): void
    {
        $argv = ['phel', 'run', '-t', 'cmd'];

        self::assertSame(
            ['phel', 'run', '-t', 'cmd'],
            new ArgvInputSanitizer()->sanitize($argv),
        );
    }

    public function test_collects_multiple_options_before_command(): void
    {
        $argv = ['phel', 'run', '-t', '--with-time', '--clear-opcache', 'cmd'];

        self::assertSame(
            ['phel', 'run', '-t', '--with-time', '--clear-opcache', 'cmd'],
            new ArgvInputSanitizer()->sanitize($argv),
        );
    }

    public function test_only_options_without_command(): void
    {
        $argv = ['phel', 'run', '--with-time'];

        self::assertSame(
            ['phel', 'run', '--with-time'],
            new ArgvInputSanitizer()->sanitize($argv),
        );
    }

    public function test_separates_command_args_with_double_dash(): void
    {
        $argv = ['phel', 'run', '-t', 'cmd', 'arg1', 'arg2'];

        self::assertSame(
            ['phel', 'run', '-t', 'cmd', '--', 'arg1', 'arg2'],
            new ArgvInputSanitizer()->sanitize($argv),
        );
    }

    public function test_unknown_option_is_treated_as_command(): void
    {
        // --foo is not a known RUN_OPTION, so option collection stops and it
        // becomes the command token; the rest is forwarded after a separator.
        $argv = ['phel', 'run', '--foo', 'arg1'];

        self::assertSame(
            ['phel', 'run', '--foo', '--', 'arg1'],
            new ArgvInputSanitizer()->sanitize($argv),
        );
    }

    public function test_command_args_following_command_without_run_options(): void
    {
        $argv = ['phel', 'run', 'cmd', 'arg1'];

        self::assertSame(
            ['phel', 'run', 'cmd', '--', 'arg1'],
            new ArgvInputSanitizer()->sanitize($argv),
        );
    }
}
