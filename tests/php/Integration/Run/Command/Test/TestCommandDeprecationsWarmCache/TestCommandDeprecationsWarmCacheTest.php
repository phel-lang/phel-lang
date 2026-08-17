<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandDeprecationsWarmCache;

use Override;
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
use function substr_count;
use function sys_get_temp_dir;

/**
 * A compiler deprecation is found while a namespace compiles. The compiled
 * code cache used to serve a warm namespace without recompiling it, so
 * `PHEL_WARN_DEPRECATIONS=1 phel test` reported everything on a cold cache
 * and nothing on a warm one, and a CI step running after a plain `phel test`
 * always saw the warm one (#3222). The notices a compile finds are now
 * stored with the cache entry and replayed on a hit.
 */
final class TestCommandDeprecationsWarmCacheTest extends TestCase
{
    private const string NOTICE = 'Using "php/new" for constructing a PHP object is deprecated';

    private string $projectDir;

    private string $repoRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 7);
        $this->projectDir = sys_get_temp_dir() . '/phel-deprecations-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/tests', 0o755, true);
        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\nreturn new \\Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs(['src'])->withTestDirs(['tests'])->withVendorDir('');\n",
        );
        file_put_contents(
            $this->projectDir . '/tests/probe_test.phel',
            "(ns app.probe-test\n  (:require phel.test :refer [deftest is]))\n\n"
            . "(deftest test-probe\n  (is (php/instanceof (php/new \\stdClass) \\stdClass)))\n",
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_a_warm_cache_reports_the_same_deprecations_as_a_cold_one(): void
    {
        [$exitCode, $cold] = $this->runPhelTest('PHEL_WARN_DEPRECATIONS=1');
        self::assertSame(0, $exitCode, $cold);
        self::assertStringContainsString(self::NOTICE, $cold, 'the cold compile reports the deprecation');

        [$exitCode, $warm] = $this->runPhelTest('PHEL_WARN_DEPRECATIONS=1');
        self::assertSame(0, $exitCode, $warm);
        self::assertStringContainsString(self::NOTICE, $warm, 'the warm run, served from the cache, reports it too');
        self::assertSame(
            substr_count($cold, self::NOTICE),
            substr_count($warm, self::NOTICE),
            'once per run, cold or warm',
        );
    }

    public function test_a_check_after_a_plain_run_still_reports(): void
    {
        [$exitCode, $plain] = $this->runPhelTest('PHEL_WARN_DEPRECATIONS=');
        self::assertSame(0, $exitCode, $plain);
        self::assertStringNotContainsString(self::NOTICE, $plain, 'notices stay opt-in');

        [$exitCode, $checked] = $this->runPhelTest('PHEL_WARN_DEPRECATIONS=1');
        self::assertSame(0, $exitCode, $checked);
        self::assertStringContainsString(self::NOTICE, $checked, 'the flag turns on what the plain run recorded');
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runPhelTest(string $env): array
    {
        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && ' . $env . ' php ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' test 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
