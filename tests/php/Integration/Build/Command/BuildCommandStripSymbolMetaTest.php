<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Phel\Build\Infrastructure\Command\BuildCommand;
use PhelTest\Integration\Util\DirectoryUtil;
use PhelTest\Support\PerTestGacelaCache;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

use function dirname;
use function escapeshellarg;
use function file_get_contents;
use function fopen;
use function ob_end_clean;
use function ob_start;
use function rewind;
use function shell_exec;
use function sprintf;
use function stream_get_contents;
use function sys_get_temp_dir;
use function trim;

/**
 * End-to-end guarantees for the `strip-symbol-meta` build option: compiled
 * output (primary AND `(in-ns ...)` secondary) carries no `locationMeta`,
 * the stripped artifact still runs standalone, and flipping the flag
 * invalidates the mtime-based incremental cache via the on-disk marker.
 */
final class BuildCommandStripSymbolMetaTest extends TestCase
{
    private const string DEST_DIR = 'out-strip';

    private BuildCommandWorkspace $workspace;

    protected function setUp(): void
    {
        new PerTestGacelaCache()->isolate();
        $this->workspace = new BuildCommandWorkspace('strip-meta');
        $this->workspace
            ->import('phel-config-strip.php')
            ->import('phel-config-strip-off.php')
            ->import('src-strip');
        DirectoryUtil::removeDir(sys_get_temp_dir() . '/phel');
    }

    protected function tearDown(): void
    {
        $this->workspace->remove();
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_output_contains_no_symbol_meta(): void
    {
        $this->runBuild('phel-config-strip.php');

        $primary = $this->destDir() . '/stripns/core.php';
        $secondary = $this->destDir() . '/stripns/core/extra.php';
        self::assertFileExists($primary);
        self::assertFileExists($secondary);

        foreach ($this->workspace->compiledPhpFiles(self::DEST_DIR) as $file) {
            self::assertStringNotContainsString(
                'locationMeta',
                (string) file_get_contents($file),
                sprintf('Compiled artifact %s still carries symbol meta.', $file),
            );
        }

        self::assertFileExists($this->destDir() . '/.phel-strip-symbol-meta');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_stripped_artifact_runs_standalone(): void
    {
        $this->runBuild('phel-config-strip.php');

        $output = trim((string) shell_exec('php ' . escapeshellarg($this->writeRunner()) . ' 2>&1'));

        self::assertStringContainsString('>> STRIPPED extra-ok', $output);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_flag_flip_invalidates_incremental_cache(): void
    {
        // Subprocess builds: the flag comes from phel-config.php, and Gacela
        // config does not reliably re-bootstrap inside one process. Each
        // build is a fresh `bin/phel build` with the config rewritten.
        $primary = $this->destDir() . '/stripns/core.php';

        // First build without stripping, incremental cache enabled.
        $this->shellBuild('phel-config-strip-off.php');
        self::assertStringContainsString('locationMeta', (string) file_get_contents($primary));

        // Same sources, cache still enabled: only the flag changed. The
        // mtime-based cache alone would reuse the meta-carrying output; the
        // marker must force a recompile.
        $this->shellBuild('phel-config-strip.php');
        self::assertStringNotContainsString('locationMeta', (string) file_get_contents($primary));

        // And back: a non-strip build must not reuse stripped files as cache.
        $this->shellBuild('phel-config-strip-off.php');
        self::assertStringContainsString('locationMeta', (string) file_get_contents($primary));
        self::assertFileDoesNotExist($this->destDir() . '/.phel-strip-symbol-meta');
    }

    /**
     * Run `bin/phel build` in a subprocess with the given fixture config
     * installed as the workspace's phel-config.php.
     */
    private function shellBuild(string $configFile): void
    {
        copy($this->workspace->path($configFile), $this->workspace->path('phel-config.php'));

        $phelBin = dirname(__DIR__, 5) . '/bin/phel';
        $cmd = sprintf(
            'cd %s && php %s build --no-source-map 2>&1',
            escapeshellarg($this->workspace->root()),
            escapeshellarg($phelBin),
        );
        $output = (string) shell_exec($cmd);

        self::assertFileExists(
            $this->destDir() . '/stripns/core.php',
            "Build produced no primary output. Output:\n" . $output,
        );
    }

    private function writeRunner(): string
    {
        return $this->workspace->writeRunner(self::DEST_DIR, 'stripns/core.php');
    }

    private function destDir(): string
    {
        return $this->workspace->path(self::DEST_DIR);
    }

    private function runBuild(string $configFile, bool $noCache = true): void
    {
        $this->workspace->bootstrapGacela($configFile);

        $args = ['--no-source-map' => true];
        if ($noCache) {
            $args['--no-cache'] = true;
        }

        ob_start();
        $output = new StreamOutput(fopen('php://memory', 'w+') ?: throw new RuntimeException('Cannot open memory stream'));
        $exit = new BuildCommand()->run(new ArrayInput($args), $output);
        ob_end_clean();

        if ($exit !== 0) {
            rewind($output->getStream());
            self::fail('Build command failed (exit=' . $exit . "):\n" . stream_get_contents($output->getStream()));
        }
    }
}
