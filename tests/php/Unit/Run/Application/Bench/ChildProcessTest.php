<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Bench;

use Phel\Run\Application\Bench\ChildProcess;
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function sys_get_temp_dir;

final class ChildProcessTest extends TestCase
{
    public function test_it_captures_more_stderr_than_a_pipe_buffer_holds(): void
    {
        // 256 KiB on stderr before any stdout: reading one pipe to the end
        // before the other would block both processes forever.
        [$status, $stdout, $stderr] = new ChildProcess()->capture(
            [PHP_BINARY, '-r', 'fwrite(STDERR, str_repeat("x", 262144)); echo "done"; exit(3);'],
            sys_get_temp_dir(),
        );

        self::assertSame(3, $status);
        self::assertSame('done', $stdout);
        self::assertSame(str_repeat('x', 262144), $stderr);
    }
}
