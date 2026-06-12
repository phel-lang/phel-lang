<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Phel\Run\Infrastructure\Command\WatchRerunCommandBuilder;
use PHPUnit\Framework\TestCase;

final class WatchRerunCommandBuilderTest extends TestCase
{
    public function test_strips_the_watch_flag_and_keeps_every_other_argument(): void
    {
        $command = new WatchRerunCommandBuilder('/usr/bin/php')->build(
            ['bin/phel', 'test', '--watch', '--filter=my-test', 'tests/foo.phel'],
            '/project/bin/phel',
        );

        self::assertSame(
            "'/usr/bin/php' '/project/bin/phel' 'test' '--filter=my-test' 'tests/foo.phel'",
            $command,
        );
    }

    public function test_uses_script_filename_over_argv_zero(): void
    {
        $command = new WatchRerunCommandBuilder('/usr/bin/php')->build(
            ['relative/phel', 'test'],
            '/absolute/bin/phel',
        );

        self::assertStringNotContainsString('relative/phel', $command);
        self::assertStringContainsString("'/absolute/bin/phel'", $command);
    }

    public function test_escapes_shell_metacharacters_in_arguments(): void
    {
        $command = new WatchRerunCommandBuilder('/usr/bin/php')->build(
            ['bin/phel', 'test', '--filter=a b; rm -rf /'],
            '/project/bin/phel',
        );

        self::assertStringContainsString("'--filter=a b; rm -rf /'", $command);
    }
}
