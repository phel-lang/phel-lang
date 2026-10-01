<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;

use const PHP_BINARY;

/**
 * A buffer opened without the removable flag can never be closed, and it
 * strands every buffer under it, so the case cannot run inside the test
 * process without swallowing the output of every later test.
 */
final class WithOutputBufferSubprocessTest extends TestCase
{
    public function test_a_buffer_php_refuses_to_close_fails_with_out_str_instead_of_hanging(): void
    {
        $root = dirname(__DIR__, 4);

        // max_execution_time turns a regression (an endless cleanup loop) into a
        // failure without the expected message instead of a hung test run.
        $process = Subprocess::run(
            [PHP_BINARY, '-d', 'max_execution_time=30', $root . '/bin/phel', 'eval', '(count (with-out-str (php/ob_start nil 0 0) (print "x")))'],
            $root,
            env: ['PHEL_NO_OPCACHE_REEXEC' => '1', 'PATH' => (string) getenv('PATH'), 'HOME' => (string) getenv('HOME'), 'TMPDIR' => (string) (getenv('TMPDIR') ?: '/tmp')],
        );

        self::assertNotSame(0, $process->exitCode);
        self::assertStringContainsString(
            'left an output buffer open that PHP cannot close',
            $process->stdout . $process->stderr,
        );
    }
}
