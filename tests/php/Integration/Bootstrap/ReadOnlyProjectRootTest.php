<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use PHPUnit\Framework\TestCase;

use function bin2hex;
use function chmod;
use function dirname;
use function escapeshellarg;
use function implode;
use function mkdir;
use function proc_close;
use function proc_open;
use function random_bytes;
use function realpath;
use function rmdir;
use function stream_get_contents;
use function sys_get_temp_dir;

use const PHP_BINARY;

/**
 * Runs the source `bin/phel` from a read-only working directory with no HOME,
 * the shape of the NixOS build sandbox where the resolved project root is not
 * writable. Before this guard existed Gacela's file cache (and the build
 * dependency-graph cache) fataled on `mkdir`, which broke the nixpkgs
 * `versionCheckHook` (`phel --help`).
 */
final class ReadOnlyProjectRootTest extends TestCase
{
    private string $binPath;

    private string $readOnlyDir;

    protected function setUp(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $this->binPath = $repoRoot . '/bin/phel';

        $this->readOnlyDir = realpath(sys_get_temp_dir())
            . '/phel-readonly-' . bin2hex(random_bytes(6));
        mkdir($this->readOnlyDir, 0755, true);
        chmod($this->readOnlyDir, 0555);

        if (@mkdir($this->readOnlyDir . '/probe')) {
            @rmdir($this->readOnlyDir . '/probe');
            self::markTestSkipped('chmod has no effect (running as root?)');
        }
    }

    protected function tearDown(): void
    {
        @chmod($this->readOnlyDir, 0755);
        @rmdir($this->readOnlyDir);
    }

    public function test_help_prints_the_version_from_a_read_only_cwd(): void
    {
        $result = $this->runPhelInReadOnlyDir(['--help']);

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertStringContainsString('Phel v', $result['stdout']);
        self::assertStringNotContainsString('was not created', $result['stderr']);
    }

    public function test_doc_works_from_a_read_only_cwd(): void
    {
        $result = $this->runPhelInReadOnlyDir(['doc', 'map']);

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertStringContainsString('map', $result['stdout']);
        self::assertStringNotContainsString('was not created', $result['stderr']);
    }

    public function test_eval_works_from_a_read_only_cwd(): void
    {
        $result = $this->runPhelInReadOnlyDir(['eval', '(+ 40 2)']);

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertStringContainsString('42', $result['stdout']);
    }

    /**
     * @param list<string> $args
     *
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runPhelInReadOnlyDir(array $args): array
    {
        $cmd = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->binPath),
            ...array_map(escapeshellarg(...), $args),
        ]);

        // No HOME mirrors the sandbox; keep PATH + TMPDIR so PHP still works.
        $env = array_diff_key(getenv(), ['HOME' => '']);

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->readOnlyDir, $env);
        self::assertIsResource($proc, 'proc_open failed');

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
