<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Shared\VersionResolver;
use PhelTest\Integration\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function implode;
use function mkdir;
use function ob_end_clean;
use function ob_start;
use function rewind;
use function shell_exec;
use function sprintf;
use function str_replace;
use function stream_get_contents;
use function trim;
use function var_export;

/**
 * Runs the entry point `phel build` writes, the way a deployment does: plain
 * `php out/main.php`, no Phel CLI. Core fns that reach a facade (`read-string`,
 * `promise`, `eval`, `phel.edn`) need the runtime booted by that file.
 */
final class BuildCommandEntryPointE2ETest extends TestCase
{
    private const string DEST_DIR = 'out-entry-e2e';

    private BuildCommandWorkspace $workspace;

    protected function setUp(): void
    {
        $this->workspace = new BuildCommandWorkspace('entry-e2e');
        $this->workspace
            ->import('phel-config-entry-e2e.php')
            ->import('src-entry-e2e');
        DirectoryUtil::removeDir(sys_get_temp_dir() . '/phel');
    }

    protected function tearDown(): void
    {
        $this->workspace->remove();
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_built_entry_point_runs_core_fns_backed_by_a_facade(): void
    {
        $this->runBuild();
        $this->writeProjectAutoloader();

        $mainPhp = $this->workspace->path(self::DEST_DIR . '/main.php');
        $output = trim((string) shell_exec('php ' . escapeshellarg($mainPhp) . ' 2>&1'));

        self::assertSame(
            "read-string: (+ 1 2)\npromise: 42\neval: 3\nedn: {:a 1}",
            $output,
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_built_entry_point_rejects_an_incompatible_runtime_before_program_output(): void
    {
        $this->runBuild();
        $this->writeProjectAutoloader();

        $mainPhp = $this->workspace->path(self::DEST_DIR . '/main.php');
        $contents = (string) file_get_contents($mainPhp);
        $stamped = str_replace(VersionResolver::current(), 'v0.0.0', $contents, $replacements);
        self::assertGreaterThan(0, $replacements, 'The entry point must stamp the build version');
        file_put_contents($mainPhp, $stamped);

        exec('php ' . escapeshellarg($mainPhp) . ' 2>&1', $output, $exitCode);
        $diagnostic = implode("\n", $output);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('v0.0.0', $diagnostic);
        self::assertStringContainsString(VersionResolver::current(), $diagnostic);
        self::assertStringContainsString('phel build', $diagnostic);
        self::assertStringNotContainsString('read-string:', $diagnostic);
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

    private function runBuild(): void
    {
        $this->workspace->bootstrapGacela('phel-config-entry-e2e.php');

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
