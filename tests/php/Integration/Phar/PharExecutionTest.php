<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phar;

use PHPUnit\Framework\TestCase;

use function dirname;
use function sprintf;

/**
 * End-to-end test that runs the built `phel.phar` against a minimal user
 * project. Guards against two regressions:
 *
 *   1. Duplicate namespace warnings caused by the same phel core file being
 *      reached via both a `..`-prefixed and a clean path string inside the PHAR.
 *   2. Cache write failures caused by the cache dir resolving to a `phar://`
 *      location (which is read-only under `phar.readonly=1`).
 *
 * The test is skipped when the PHAR has not been built. Run `build/phar.sh`
 * first to generate it.
 */
final class PharExecutionTest extends TestCase
{
    private const string PHAR_RELATIVE_PATH = '/build/out/phel.phar';

    private string $pharPath;

    private string $tempProjectDir;

    protected function setUp(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $this->pharPath = $repoRoot . self::PHAR_RELATIVE_PATH;

        if (!is_file($this->pharPath)) {
            self::markTestSkipped(sprintf(
                'phel.phar not found at %s; run build/phar.sh to generate it',
                $this->pharPath,
            ));
        }

        $this->tempProjectDir = sys_get_temp_dir() . '/phel-phar-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempProjectDir, 0755, true);
        file_put_contents(
            $this->tempProjectDir . '/main.phel',
            "(ns local\\main)\n(println \"phar works\")\n",
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempProjectDir)) {
            $this->removeDir($this->tempProjectDir);
        }
    }

    public function test_phar_runs_from_external_directory_without_warnings(): void
    {
        $result = $this->runPhar(['run', 'main.phel']);

        self::assertSame(
            0,
            $result['exit'],
            sprintf("Non-zero exit.\nstdout:\n%s\nstderr:\n%s", $result['stdout'], $result['stderr']),
        );
        self::assertStringContainsString('phar works', $result['stdout']);

        self::assertStringNotContainsString(
            'defined in multiple locations',
            $result['stderr'],
            'PHAR should not emit duplicate-namespace warnings for phel core files',
        );

        self::assertStringNotContainsString(
            'phar.readonly',
            $result['stderr'],
            'Compiled-code cache must not be written inside the (read-only) PHAR',
        );
        self::assertStringNotContainsString(
            'Phel cache: failed to write',
            $result['stderr'],
            'atomicWrite must succeed (cache dir must resolve to a writable location)',
        );
    }

    /**
     * @param list<string> $args
     *
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runPhar(array $args): array
    {
        $cmd = sprintf(
            '%s %s',
            escapeshellarg($this->pharPath),
            implode(' ', array_map(escapeshellarg(...), $args)),
        );

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, $this->tempProjectDir);
        self::assertIsResource($proc, 'proc_open failed');

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function removeDir(string $dir): void
    {
        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
