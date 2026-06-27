<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * Drives the real `bin/phel` as a subprocess to prove the OPcache re-exec is
 * correctness-preserving: a warm run with the feature on must match a run with
 * it opted out, bit for bit on stdout and exit code. Speed is not asserted —
 * the re-exec only ever needs to be transparent.
 */
final class OpcacheReexecTest extends TestCase
{
    private string $repoRoot;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 6);
        $this->projectDir = sys_get_temp_dir() . '/phel-opcache-reexec-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0o755, true);

        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );

        file_put_contents(
            $this->projectDir . '/main.phel',
            "(ns local\\main)\n(println (+ 1 2 3))\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function test_warm_run_with_opcache_reexec_matches_opted_out_run(): void
    {
        // Cold run primes the compiled-code cache and (when re-exec fires) the
        // OPcache file cache, so the asserted run below is a genuine warm one.
        $this->runPhel([]);

        [$warmExit, $warmOut] = $this->runPhel([]);
        [$optOutExit, $optOutOut] = $this->runPhel(['PHEL_NO_OPCACHE_REEXEC' => '1']);

        self::assertSame(0, $warmExit);
        self::assertSame($optOutExit, $warmExit);
        self::assertSame($optOutOut, $warmOut);
        self::assertStringContainsString('6', $warmOut);
    }

    /**
     * @param array<string, string> $env
     *
     * @return array{0: int, 1: string} exit code and combined output
     */
    private function runPhel(array $env): array
    {
        $prefix = '';
        foreach ($env as $name => $value) {
            $prefix .= $name . '=' . escapeshellarg($value) . ' ';
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && ' . $prefix . 'php ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' run main.phel 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        exec('rm -rf ' . escapeshellarg($dir));
    }
}
