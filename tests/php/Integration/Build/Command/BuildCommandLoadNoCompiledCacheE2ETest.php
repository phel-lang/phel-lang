<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Build\Infrastructure\Command\BuildCommand;
use PhelTest\Integration\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

use function dirname;
use function escapeshellarg;
use function fopen;
use function is_dir;
use function rename;
use function shell_exec;
use function sprintf;
use function stream_get_contents;
use function trim;
use function var_export;

/**
 * Regression for #2648: with the compiled-code cache off, a `phel build` must
 * still emit the `(load ...)` secondaries next to their primary. The harvester
 * used to copy them out of that cache, so disabling the cache silently dropped
 * every secondary and the bundle fataled with "Cannot locate … for (load ...)"
 * the moment the primary loaded.
 *
 * Uses the same `src-load-e2e` fixture as {@see BuildCommandLoadE2ETest}, only
 * with `withEnableCompiledCodeCache(false)`.
 */
final class BuildCommandLoadNoCompiledCacheE2ETest extends TestCase
{
    private const string DEST_DIR = __DIR__ . '/out-load-e2e-no-cache';

    private const string SRC_DIR = __DIR__ . '/src-load-e2e';

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(self::DEST_DIR);
        self::restoreSrcDir();
        DirectoryUtil::removeDir(sys_get_temp_dir() . '/phel');
    }

    protected function setUp(): void
    {
        DirectoryUtil::removeDir(self::DEST_DIR);
        self::restoreSrcDir();
        DirectoryUtil::removeDir(sys_get_temp_dir() . '/phel');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_emits_loaded_secondaries_without_the_compiled_code_cache(): void
    {
        $this->runBuild();

        self::assertFileExists(self::DEST_DIR . '/loade2e/core.php');
        self::assertFileExists(self::DEST_DIR . '/loade2e/core/util.php');
        self::assertFileExists(self::DEST_DIR . '/loade2e/core/greet.php');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_built_artifact_runs_without_the_source_tree(): void
    {
        $this->runBuild();
        $this->hideSrcDir();

        $runner = $this->writeRunner();
        $output = trim((string) shell_exec('php ' . escapeshellarg($runner) . ' 2>&1'));

        self::assertStringContainsString('>> Hello, world!', $output);
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

    private function runBuild(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('phel-config-load-e2e-no-compiled-cache.php');
        });

        ob_start();
        $output = new StreamOutput(fopen('php://memory', 'w+') ?: throw new RuntimeException('Cannot open memory stream'));
        $exit = new BuildCommand()->run(new ArrayInput(['--no-source-map' => true]), $output);
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
