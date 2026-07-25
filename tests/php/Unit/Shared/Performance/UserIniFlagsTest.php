<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Performance;

use Phel\Shared\Performance\UserIniFlags;
use PHPUnit\Framework\TestCase;

final class UserIniFlagsTest extends TestCase
{
    public function test_extracts_detached_ini_flags(): void
    {
        self::assertSame(
            ['-d', 'display_errors=stderr', '-d', 'precision=7'],
            UserIniFlags::extract(
                ['php', '-d', 'display_errors=stderr', '-d', 'precision=7', 'bin/phel', 'run', 'x.phel'],
                ['bin/phel', 'run', 'x.phel'],
            ),
        );
    }

    public function test_extracts_glued_ini_flags(): void
    {
        self::assertSame(
            ['-ddisplay_errors=stderr'],
            UserIniFlags::extract(
                ['php', '-ddisplay_errors=stderr', 'bin/phel', 'run', 'x.phel'],
                ['bin/phel', 'run', 'x.phel'],
            ),
        );
    }

    public function test_extracts_ini_file_selection_flags(): void
    {
        // `-n` and `-c` decide which php.ini the successor reads, so they belong
        // with the `-d` overrides: dropping them changes the effective config
        // just as silently.
        self::assertSame(
            ['-n', '-c', '/etc/custom/php.ini', '-d', 'precision=7'],
            UserIniFlags::extract(
                ['php', '-n', '-c', '/etc/custom/php.ini', '-d', 'precision=7', 'bin/phel', 'run', 'x.phel'],
                ['bin/phel', 'run', 'x.phel'],
            ),
        );
    }

    public function test_returns_no_flags_when_none_were_passed(): void
    {
        self::assertSame(
            [],
            UserIniFlags::extract(
                ['php', 'bin/phel', 'run', 'x.phel'],
                ['bin/phel', 'run', 'x.phel'],
            ),
        );
    }

    public function test_bails_out_when_the_script_arguments_do_not_line_up(): void
    {
        // The `ps` fallback joins arguments with spaces, so `-d error_log=/tmp/a b.log`
        // comes back as two tokens and the suffix no longer matches argv. Guessing
        // would splice a stray `b.log` into the child's argv; carrying nothing over
        // just reproduces the old behaviour.
        self::assertSame(
            [],
            UserIniFlags::extract(
                ['php', '-d', 'error_log=/tmp/a', 'b.log', 'bin/phel', 'run', 'x.phel'],
                ['bin/phel', 'run', 'x.phel'],
            ),
        );
    }

    public function test_bails_out_on_an_option_it_does_not_model(): void
    {
        self::assertSame(
            [],
            UserIniFlags::extract(
                ['php', '-z', '/tmp/ext.so', '-d', 'precision=7', 'bin/phel', 'run', 'x.phel'],
                ['bin/phel', 'run', 'x.phel'],
            ),
        );
    }

    public function test_bails_out_on_a_dangling_value_less_flag(): void
    {
        self::assertSame(
            [],
            UserIniFlags::extract(
                ['php', '-d', 'bin/phel'],
                ['bin/phel'],
            ),
        );
    }

    public function test_bails_out_when_the_process_command_line_is_unknown(): void
    {
        self::assertSame([], UserIniFlags::extract([], ['bin/phel', 'run', 'x.phel']));
    }

    public function test_bails_out_when_the_script_argv_is_unknown(): void
    {
        self::assertSame([], UserIniFlags::extract(['php', '-d', 'precision=7', 'bin/phel'], []));
    }

    public function test_bails_out_when_the_process_command_line_is_shorter_than_the_script_argv(): void
    {
        // `php -r` reports argv[0] as "Standard input code", which can never be a
        // suffix of the real command line.
        self::assertSame([], UserIniFlags::extract(['php', '-r', 'echo 1;'], ['Standard input code']));
    }
}
