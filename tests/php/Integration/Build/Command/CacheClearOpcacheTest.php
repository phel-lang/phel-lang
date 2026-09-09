<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Phel\Shared\Performance\OpcacheFileCache;
use PhelTest\Support\RemoveDirTrait;
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
use function scandir;
use function sprintf;
use function sys_get_temp_dir;

/**
 * Drives the real `bin/phel` as a subprocess, because the OPcache file cache
 * only exists once the CLI has re-exec'd itself with `opcache.file_cache`
 * pointing at `<phel-dir>/opcache` (#3268).
 */
final class CacheClearOpcacheTest extends TestCase
{
    use RemoveDirTrait;

    private const string FOREIGN_ID = 'cf11b0a41c1f745a1bfd730ac5e2b089';

    private string $repoRoot;

    private string $projectDir;

    private string $opcacheDir;

    private string $gacelaCacheDir;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 5);
        $this->projectDir = sys_get_temp_dir() . '/phel-opcache-clear-' . bin2hex(random_bytes(6));
        $this->opcacheDir = $this->projectDir . '/.phel/opcache';
        $this->gacelaCacheDir = $this->projectDir . '/.gacela';

        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );

        file_put_contents(
            $this->projectDir . '/main.phel',
            "(ns local.main)\n(println (+ 1 2 3))\n",
        );

        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\n\ndeclare(strict_types=1);\n\n"
            . "return new Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs(['.'])\n"
            . "    ->withKeepGeneratedTempFiles(getenv('PHEL_TEST_KEEP_TEMP') !== false)\n"
            . "    ->withTempDir(__DIR__ . '/temp');\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function test_cache_clear_empties_the_opcache_directory_and_keeps_it(): void
    {
        $this->runPhel(['run', 'main.phel']);
        $this->skipUnlessFileCacheIsUsed();

        // Planted under the live system id and outside the temp root, so only
        // cache:clear can remove it: the startup prune keeps the live subtree
        // and only sweeps orphans below the system temp directory.
        $liveId = new OpcacheFileCache($this->opcacheDir)->entries()[0];
        $survivor = $this->opcacheDir . '/' . $liveId . '/opt/app/boot.php.bin';
        mkdir(dirname($survivor), 0o755, true);
        file_put_contents($survivor, 'bin');

        [$clearExit, $clearOutput] = $this->runPhel(['cache:clear']);

        self::assertSame(0, $clearExit, $clearOutput);
        self::assertStringContainsString($this->opcacheDir, $clearOutput);
        self::assertDirectoryExists($this->opcacheDir);
        self::assertFileDoesNotExist($survivor);

        // PHP aborts at startup when opcache.file_cache is missing, so the run
        // right after a clear is the one that proves the directory survived.
        [$runExit, $runOutput] = $this->runPhel(['run', 'main.phel']);

        self::assertSame(0, $runExit, $runOutput);
        self::assertStringNotContainsString('file_cache', $runOutput);
        self::assertStringContainsString('6', $runOutput);
    }

    public function test_a_run_prunes_a_subtree_left_by_another_system_id(): void
    {
        $this->runPhel(['run', 'main.phel']);
        $this->skipUnlessFileCacheIsUsed();

        $foreignSubtree = $this->opcacheDir . '/' . self::FOREIGN_ID . '/opt/app';
        mkdir($foreignSubtree, 0o755, true);
        file_put_contents($foreignSubtree . '/boot.php.bin', 'stale');

        $this->runPhel(['run', 'main.phel']);

        self::assertDirectoryDoesNotExist($this->opcacheDir . '/' . self::FOREIGN_ID);
        self::assertNotSame([], new OpcacheFileCache($this->opcacheDir)->entries());
    }

    public function test_eval_leaves_no_generated_temp_file_behind(): void
    {
        // The keep-on run first, so the empty result below is a cleanup and not
        // an eval that never wrote to this directory at all.
        $this->runPhel(['eval', '(+ 1 2 3)'], ['PHEL_TEST_KEEP_TEMP' => '1']);
        self::assertNotSame([], $this->tempDirEntries());

        // Every cache goes, so the second eval is as cold as the first one: a
        // warm compiled-code cache needs no generated temp file at all, and a
        // warm Gacela config cache would replay the keep-on flag.
        $this->removeDir($this->projectDir . '/temp');
        $this->removeDir($this->projectDir . '/.phel');
        $this->removeDir($this->gacelaCacheDir);

        [$exitCode, $output] = $this->runPhel(['eval', '(+ 1 2 3)']);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('6', $output);
        self::assertDirectoryExists($this->projectDir . '/temp');
        self::assertSame([], $this->tempDirEntries());
    }

    /**
     * @return list<string>
     */
    private function tempDirEntries(): array
    {
        $tempDir = $this->projectDir . '/temp';
        if (!is_dir($tempDir)) {
            return [];
        }

        $entries = [];
        foreach (scandir($tempDir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function skipUnlessFileCacheIsUsed(): void
    {
        if (!is_dir($this->opcacheDir) || new OpcacheFileCache($this->opcacheDir)->entries() === []) {
            self::markTestSkipped('OPcache file cache did not fire on this host; nothing to clear.');
        }
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     *
     * @return array{0: int, 1: string} exit code and combined output
     */
    private function runPhel(array $args, array $env = []): array
    {
        $quoted = '';
        foreach ($args as $arg) {
            $quoted .= ' ' . escapeshellarg($arg);
        }

        // Gacela caches the merged app config, and the whole suite shares one
        // cache dir through GACELA_CACHE_DIR; a per-project one keeps a run
        // here from replaying the config of the run before it.
        $env['GACELA_CACHE_DIR'] = $this->gacelaCacheDir;

        $prefix = '';
        foreach ($env as $name => $value) {
            $prefix .= $name . '=' . escapeshellarg($value) . ' ';
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && ' . $prefix . 'php ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . $quoted . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
