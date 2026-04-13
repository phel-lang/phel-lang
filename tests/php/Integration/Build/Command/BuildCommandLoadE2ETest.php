<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use FilesystemIterator;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Build\Infrastructure\Command\BuildCommand;
use PhelTest\Integration\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

use function dirname;
use function escapeshellarg;
use function file_get_contents;
use function fopen;
use function ob_end_clean;
use function ob_start;
use function rename;
use function rewind;
use function shell_exec;
use function sprintf;
use function stream_get_contents;
use function trim;

/**
 * End-to-end guarantees for `bin/phel build` when a namespace is split
 * across multiple files with `(load ...)` / `(in-ns ...)`.
 *
 * Fixture tree under `src-load-e2e/`:
 *   loade2e/core.phel            ; primary, loads the two secondaries
 *   loade2e/core/util.phel       ; (in-ns loade2e\core), defines format-name
 *   loade2e/core/greet.phel      ; (in-ns loade2e\core), defines greet (uses format-name)
 */
final class BuildCommandLoadE2ETest extends TestCase
{
    private const string DEST_DIR = __DIR__ . '/out-load-e2e';

    private const string SRC_DIR = __DIR__ . '/src-load-e2e';

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(self::DEST_DIR);
        self::restoreSrcDir();
        // Other test classes assume a fresh compile-code cache — drop the
        // files this class populated so we don't bleed into later tests.
        DirectoryUtil::removeDir(sys_get_temp_dir() . '/phel');
    }

    protected function setUp(): void
    {
        DirectoryUtil::removeDir(self::DEST_DIR);
        self::restoreSrcDir();
        // The compile-code cache persists across separate-process phpunit
        // tests via the shared system tmp dir; wipe it so each test starts
        // from a guaranteed cold state.
        DirectoryUtil::removeDir(sys_get_temp_dir() . '/phel');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_compiles_every_loaded_sub_file(): void
    {
        $this->runBuild();

        self::assertFileExists(self::DEST_DIR . '/loade2e/core.php');
        self::assertFileExists(self::DEST_DIR . '/loade2e/core/util.php');
        self::assertFileExists(self::DEST_DIR . '/loade2e/core/greet.php');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_built_artifact_does_not_bake_absolute_require_paths(): void
    {
        // The source-tree path is allowed to appear in informational metadata
        // (e.g. `*file*`, `start-location`) — those describe the origin for
        // error reporting and do not affect runtime behaviour. What must not
        // leak is any runtime lookup that would require the source tree to
        // still exist on disk.
        $this->runBuild();

        $forbidden = [
            'require ' . var_export(self::SRC_DIR, true),
            'require_once ' . var_export(self::SRC_DIR, true),
            "require '" . self::SRC_DIR,
            "require_once '" . self::SRC_DIR,
            'require "' . self::SRC_DIR,
            'require_once "' . self::SRC_DIR,
        ];

        foreach ($this->compiledArtifacts() as $file) {
            $content = (string) file_get_contents($file);
            foreach ($forbidden as $pattern) {
                self::assertStringNotContainsString(
                    $pattern,
                    $content,
                    sprintf('Compiled artifact %s bakes a runtime require of the source tree; the artifact would not run outside the build machine.', $file),
                );
            }
        }
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_built_main_runs_without_the_source_tree(): void
    {
        $this->runBuild();

        $this->hideSrcDir();

        $runner = $this->writeRunner();
        $output = trim((string) shell_exec('php ' . escapeshellarg($runner) . ' 2>&1'));

        self::assertStringContainsString('>> Hello, world!', $output);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cached_rebuild_still_works(): void
    {
        $this->runBuild();
        // Second build: cache hits for every sub-file, output must still be consistent.
        $this->runBuild(noCache: false);

        self::assertFileExists(self::DEST_DIR . '/loade2e/core.php');
        self::assertFileExists(self::DEST_DIR . '/loade2e/core/util.php');
        self::assertFileExists(self::DEST_DIR . '/loade2e/core/greet.php');
    }

    /**
     * @return iterable<string>
     */
    private function compiledArtifacts(): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::DEST_DIR, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    private function writeRunner(): string
    {
        $runner = self::DEST_DIR . '/run.php';
        $autoload = dirname(__DIR__, 5) . '/vendor/autoload.php';
        $compiled = self::DEST_DIR . '/loade2e/core.php';

        $code = sprintf(
            "<?php declare(strict_types=1);\nrequire_once %s;\nrequire_once %s;\n",
            var_export($autoload, true),
            var_export($compiled, true),
        );
        file_put_contents($runner, $code);

        return $runner;
    }

    private function runBuild(bool $noCache = true): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('phel-config-load-e2e.php');
        });

        $args = ['--no-source-map' => true];
        if ($noCache) {
            $args['--no-cache'] = true;
        }

        ob_start();
        $output = new StreamOutput(fopen('php://memory', 'w+') ?: throw new RuntimeException('Cannot open memory stream'));
        $exit = (new BuildCommand())->run(new ArrayInput($args), $output);
        ob_end_clean();

        if ($exit !== 0) {
            rewind($output->getStream());
            self::fail('Build command failed (exit=' . $exit . "):\n" . stream_get_contents($output->getStream()));
        }
    }

    private function hideSrcDir(): void
    {
        if (is_dir(self::SRC_DIR)) {
            rename(self::SRC_DIR, self::SRC_DIR . '.bak');
        }
    }

    private static function restoreSrcDir(): void
    {
        if (is_dir(self::SRC_DIR . '.bak')) {
            rename(self::SRC_DIR . '.bak', self::SRC_DIR);
        }
    }
}
