<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\OutsideConfiguredDirs;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function mkdir;
use function sprintf;

/**
 * `phel test` on a file outside every configured directory that requires a
 * project namespace. The first run compiled and passed; the second loaded the
 * cached namespace before its require and died at the first cross-namespace
 * call, and the runs then alternated because the failure evicted the cache
 * entry (#3187).
 *
 * The bug lives across two processes sharing one `.phel/cache`, so the test
 * runs the real command twice against a temp project whose `bench/` directory
 * is deliberately not configured.
 */
final class OutsideConfiguredDirsTest extends TestCase
{
    private string $projectDir;

    private string $repoRoot;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 7);
        $this->projectDir = sys_get_temp_dir() . '/phel-outside-dirs-' . bin2hex(random_bytes(8));

        if (!mkdir($this->projectDir . '/src/app', 0755, true) || !mkdir($this->projectDir . '/bench', 0755, true)) {
            throw new RuntimeException('Cannot create temp project dir: ' . $this->projectDir);
        }

        copy(__DIR__ . '/Fixtures/src/app/core.phel', $this->projectDir . '/src/app/core.phel');
        copy(__DIR__ . '/Fixtures/bench/probe.phel', $this->projectDir . '/bench/probe.phel');

        mkdir($this->projectDir . '/vendor', 0755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );

        // `src` is configured; `bench` is not. `phel.test` has to be reachable,
        // so the repository's stdlib is a second source directory.
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            sprintf(
                <<<'PHP'
                <?php

                declare(strict_types=1);

                use Phel\Config\PhelConfig;

                return new PhelConfig()
                    ->withSrcDirs(['src', '%s/src/phel'])
                    ->withTestDirs(['tests'])
                    ->withVendorDir('');

                PHP,
                $this->repoRoot,
            ),
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_a_warm_run_loads_the_require_before_the_file_that_needs_it(): void
    {
        [$coldExit, $coldOutput] = $this->runPhelTest();
        self::assertSame(0, $coldExit, 'cold run failed:' . PHP_EOL . $coldOutput);
        self::assertMatchesRegularExpression('/Passed:\s*1/', $coldOutput, $coldOutput);

        [$warmExit, $warmOutput] = $this->runPhelTest();
        self::assertSame(0, $warmExit, 'warm run failed:' . PHP_EOL . $warmOutput);
        self::assertStringNotContainsString('__invoke() on null', $warmOutput, $warmOutput);
        self::assertMatchesRegularExpression('/Passed:\s*1/', $warmOutput, $warmOutput);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runPhelTest(): array
    {
        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && php -d memory_limit=256M ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' test bench/probe.phel 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
