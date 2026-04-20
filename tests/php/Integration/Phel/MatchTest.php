<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phel;

use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;

/**
 * Runs `./bin/phel test tests/phel/test/match.phel` end-to-end so
 * `composer test-compiler` exercises the core.match macro on every CI
 * run (not only `composer test-core`).
 */
final class MatchTest extends TestCase
{
    public function test_match_test_suite_passes(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $command = sprintf(
            '%s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($repoRoot . '/bin/phel'),
            escapeshellarg('test') . ' ' . escapeshellarg('tests/phel/test/match.phel'),
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $repoRoot);
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);

        self::assertSame(
            0,
            $exitCode,
            sprintf(
                "`phel test tests/phel/test/match.phel` failed.\nSTDOUT:\n%s\nSTDERR:\n%s",
                $stdout,
                $stderr,
            ),
        );
        self::assertStringContainsString('Failed: 0', $stdout);
        self::assertStringContainsString('Error: 0', $stdout);
    }
}
