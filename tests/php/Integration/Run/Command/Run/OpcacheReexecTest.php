<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
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
    use RemoveDirTrait;

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

        file_put_contents(
            $this->projectDir . '/ini.phel',
            "(ns local\\ini)\n"
            . "(println \"precision:\" (php/ini_get \"precision\"))\n"
            . "(println \"reexeced:\" (php/getenv \"PHEL_OPCACHE_REEXEC_DONE\"))\n",
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

    public function test_reexec_carries_the_user_ini_flags_into_the_child_process(): void
    {
        // `-d` never reaches $_SERVER['argv'], so the re-exec used to hand the
        // child a bare argv and every user ini override vanished — which is what
        // made ini-based workarounds look like they did nothing.
        [$exitCode, $output] = $this->runPhel([], ['-d', 'precision=9'], 'ini.phel');

        self::assertSame(0, $exitCode);

        if (!str_contains($output, 'reexeced: 1')) {
            self::markTestSkipped('OPcache re-exec did not fire on this host; nothing to carry over.');
        }

        self::assertStringContainsString('precision: 9', $output);
    }

    public function test_reexec_keeps_its_own_opcache_flags_authoritative(): void
    {
        // Phel forces opcache.enable_cli / file_cache / file_cache_only because a
        // re-exec without them costs a process start and buys nothing, so a user
        // flag on those directives must lose. Everything else on the same command
        // line still has to survive.
        [$exitCode, $output] = $this->runPhel(
            [],
            ['-d', 'opcache.enable_cli=0', '-d', 'precision=9'],
            'ini.phel',
        );

        self::assertSame(0, $exitCode);

        if (!str_contains($output, 'reexeced: 1')) {
            self::markTestSkipped('OPcache re-exec did not fire on this host; nothing to carry over.');
        }

        self::assertStringContainsString('precision: 9', $output);
    }

    /**
     * @param array<string, string> $env
     * @param list<string>          $iniFlags
     *
     * @return array{0: int, 1: string} exit code and combined output
     */
    private function runPhel(array $env, array $iniFlags = [], string $script = 'main.phel'): array
    {
        $prefix = '';
        foreach ($env as $name => $value) {
            $prefix .= $name . '=' . escapeshellarg($value) . ' ';
        }

        $flags = '';
        foreach ($iniFlags as $flag) {
            $flags .= escapeshellarg($flag) . ' ';
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && ' . $prefix . 'php ' . $flags . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' run ' . escapeshellarg($script) . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }

}
