<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function array_diff_key;
use function bin2hex;
use function dirname;
use function file_put_contents;
use function getenv;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * A changed `(load ...)` sibling invalidates every file of its namespace,
 * the ones this run already required from the cache included. Deleting
 * those left their frames pointing at a missing compiled file (#3430).
 *
 * Each run is its own `bin/phel` process with its own cache, as a user's is.
 * Two in-process runs shared the test cache with every paratest worker, and
 * after a `phel.core` change the second one recompiled `phel.core` inside a
 * live process and printed nothing (#3440).
 */
final class ChangedLoadSiblingTest extends TestCase
{
    use RemoveDirTrait;

    private string $repoRoot;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 6);
        $this->projectDir = sys_get_temp_dir() . '/phel-stale-sibling-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir . '/src', 0o755, true);

        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );

        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\n\ndeclare(strict_types=1);\n\n"
            . "use Phel\\Config\\PhelConfig;\n\n"
            . "return new PhelConfig()->withSrcDirs(['src'])->withVendorDir('');\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function test_changed_load_sibling_keeps_frames_of_already_loaded_files_mapped(): void
    {
        file_put_contents(
            $this->projectDir . '/src/stale-sibling-main.phel',
            "(ns stale-sibling-main)\n\n(defn read-fifth [xs]\n  (nth xs 5))\n\n(load \"stale-sibling-part\")\n",
        );
        $this->writePart('(read-fifth [1 2 3 4 5 6])');

        $firstRun = $this->runMain();
        self::assertSame(0, $firstRun->exitCode, $firstRun->stdout . $firstRun->stderr);

        $this->writePart('(read-fifth [])');
        $secondRun = $this->runMain();
        $output = $secondRun->stdout . $secondRun->stderr;

        self::assertNotSame(0, $secondRun->exitCode, $output);
        self::assertStringContainsString('Vector index 5 out of bounds', $output);
        self::assertMatchesRegularExpression('~#\d+ \S*stale-sibling-main\.phel:4 : \(phel\\\\core\\\\nth~', $output);
        // The `at` line may name the compiled file next to the source; a frame
        // must not, or its source map was lost.
        self::assertDoesNotMatchRegularExpression('~^#\d+ \S*/compiled/~m', $output);
    }

    private function writePart(string $form): void
    {
        file_put_contents(
            $this->projectDir . '/src/stale-sibling-part.phel',
            "(in-ns stale-sibling-main)\n\n" . $form . "\n",
        );
    }

    private function runMain(): Subprocess
    {
        return Subprocess::run(
            [PHP_BINARY, '-d', 'memory_limit=256M', $this->repoRoot . '/bin/phel', 'run', 'src/stale-sibling-main.phel'],
            $this->projectDir,
            // A cache of its own: the shared test cache would let other tests
            // and paratest workers rewrite the same compiled files mid-test.
            env: [
                ...array_diff_key(getenv(), ['PHEL_CACHE_DIR' => '', 'PHEL_TEST_SHARED_CACHE_DIR' => '']),
                'PHEL_CACHE_DIR' => $this->projectDir . '/.phel-cache',
                'PHEL_NO_OPCACHE_REEXEC' => '1',
            ],
        );
    }
}
