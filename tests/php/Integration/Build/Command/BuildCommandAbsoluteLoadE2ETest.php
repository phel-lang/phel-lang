<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

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
use function file_put_contents;
use function fopen;
use function mkdir;
use function ob_end_clean;
use function ob_start;
use function rewind;
use function shell_exec;
use function sprintf;
use function stream_get_contents;
use function trim;
use function var_export;

/**
 * A classpath-absolute `(load "/extra")` skips the probe next to the caller
 * and searches the load classpath, which `phel run` publishes from the source
 * dirs. A built app has to find the file without them.
 */
final class BuildCommandAbsoluteLoadE2ETest extends TestCase
{
    private BuildCommandWorkspace $workspace;

    protected function setUp(): void
    {
        $this->workspace = new BuildCommandWorkspace('absolute-load-e2e');
        DirectoryUtil::removeDir(sys_get_temp_dir() . '/phel');
    }

    protected function tearDown(): void
    {
        $this->workspace->remove();
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_flat_layout_finds_an_absolute_load_next_to_the_caller(): void
    {
        // `abse2e.main` lives in `src/main.phel`, so `src/` holds the
        // namespace prefix and `/extra` is built next to `abse2e/main.php`.
        self::assertSame('abs!', $this->buildAndRun('absolute-load-e2e'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_namespaced_layout_finds_an_absolute_load_under_the_output_root(): void
    {
        // `nest.main` lives in `src/nest/main.phel`, so `/extra` is
        // `src/extra.phel`, built one level above the caller. The relative
        // `extra` puts `src/nest/extra.phel` next to the caller, and the
        // absolute load must not take that one.
        self::assertSame('quiet? nested!', $this->buildAndRun('absolute-load-nested-e2e'));
    }

    private function buildAndRun(string $fixture): string
    {
        $this->workspace
            ->import('phel-config-' . $fixture . '.php')
            ->import('src-' . $fixture);
        $this->runBuild('phel-config-' . $fixture . '.php');
        $this->writeProjectAutoloader();

        $mainPhp = $this->workspace->path('out-' . $fixture . '/main.php');

        return trim((string) shell_exec('php ' . escapeshellarg($mainPhp) . ' 2>&1'));
    }

    /**
     * The entry point loads `<project>/vendor/autoload.php`; point it at this
     * checkout's autoloader.
     */
    private function writeProjectAutoloader(): void
    {
        mkdir($this->workspace->path('vendor'));
        file_put_contents(
            $this->workspace->path('vendor/autoload.php'),
            sprintf("<?php\n\nreturn require %s;\n", var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true)),
        );
    }

    private function runBuild(string $config): void
    {
        $this->workspace->bootstrapGacela($config);

        ob_start();
        $output = new StreamOutput(fopen('php://memory', 'w+') ?: throw new RuntimeException('Cannot open memory stream'));
        $exit = new BuildCommand()->run(new ArrayInput(['--no-source-map' => true, '--no-cache' => true]), $output);
        ob_end_clean();

        if ($exit !== 0) {
            rewind($output->getStream());
            self::fail('Build command failed (exit=' . $exit . "):\n" . stream_get_contents($output->getStream()));
        }
    }
}
