<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function implode;
use function mkdir;
use function random_bytes;
use function realpath;
use function sys_get_temp_dir;

/**
 * Runs the source `bin/phel` against a temp project that has its own
 * `phel-config.php` but NO local `vendor/` — the shape of a global/PHAR
 * install. Before #2640 the launcher anchored the project root to the
 * autoloader it walked up to (the phel-lang checkout's own `vendor/`), so
 * `phel config` reported the binary's directory instead of the user's project.
 */
final class ProjectRootResolutionTest extends TestCase
{
    private string $binPath;

    private string $projectDir;

    protected function setUp(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $this->binPath = $repoRoot . '/bin/phel';

        $this->projectDir = realpath(sys_get_temp_dir())
            . '/phel-root-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir . '/src', 0755, true);
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\n\nreturn (new \\Phel\\Config\\PhelConfig())->withSrcDirs(['src']);\n",
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir . '/phel-config.php');
        @rmdir($this->projectDir . '/src');
        @rmdir($this->projectDir);
    }

    public function test_config_reports_the_cwd_project_root_not_the_binary_dir(): void
    {
        $result = $this->runPhelInDir($this->projectDir, ['config']);

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertStringContainsString(
            ' - project root: ' . $this->projectDir,
            $result['stdout'],
            'project root must be the CWD project, not the binary/autoloader directory',
        );
    }

    public function test_walks_up_to_the_project_root_from_a_subdirectory(): void
    {
        $subdir = $this->projectDir . '/src';
        $result = $this->runPhelInDir($subdir, ['config']);

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertStringContainsString(
            ' - project root: ' . $this->projectDir,
            $result['stdout'],
            'running from a subdirectory must resolve up to the nearest phel-config.php',
        );
    }

    /**
     * @param list<string> $args
     *
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runPhelInDir(string $cwd, array $args): array
    {
        $cmd = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->binPath),
            ...array_map(escapeshellarg(...), $args),
        ]);

        $process = Subprocess::run($cmd, $cwd);

        return ['exit' => $process->exitCode, 'stdout' => $process->stdout, 'stderr' => $process->stderr];
    }
}
