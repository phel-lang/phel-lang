<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\TestWorkerHandle;
use PHPUnit\Framework\TestCase;

final class TestWorkerHandleTest extends TestCase
{
    public function test_command_splices_opcache_flags_between_php_binary_and_script(): void
    {
        $cmd = TestWorkerHandle::buildCommand(
            '/usr/bin/php',
            '/app/bin/phel',
            ['-d', 'opcache.enable_cli=1', '-d', 'opcache.file_cache=/var/phel/opcache-workers'],
        );

        // -d flags must precede the script so PHP applies them; the script and
        // subcommand stay last so argv parsing is unchanged.
        self::assertSame(
            [
                '/usr/bin/php',
                '-d', 'opcache.enable_cli=1',
                '-d', 'opcache.file_cache=/var/phel/opcache-workers',
                '/app/bin/phel', '_test-worker',
            ],
            $cmd,
        );
    }

    public function test_command_without_opcache_flags_is_the_plain_worker_invocation(): void
    {
        self::assertSame(
            ['/usr/bin/php', '/app/bin/phel', '_test-worker'],
            TestWorkerHandle::buildCommand('/usr/bin/php', '/app/bin/phel', []),
        );
    }
}
