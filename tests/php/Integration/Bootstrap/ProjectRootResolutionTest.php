<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function mkdir;
use function random_bytes;
use function realpath;
use function sys_get_temp_dir;

final class ProjectRootResolutionTest extends TestCase
{
    use RemoveDirTrait;

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
        $this->removeDir($this->projectDir);
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

    public function test_run_uses_the_project_source_directory_from_a_subdirectory(): void
    {
        mkdir($this->projectDir . '/src/app', 0755, true);
        mkdir($this->projectDir . '/sub/src/app', 0755, true);
        file_put_contents($this->projectDir . '/src/app/main.phel', '(ns app.main) (println "project-source")');
        file_put_contents($this->projectDir . '/sub/src/app/main.phel', '(ns app.main) (println "cwd-source")');

        $result = $this->runPhelInDir($this->projectDir . '/sub', ['run', 'app.main']);

        self::assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        self::assertSame('project-source', trim($result['stdout']));
    }

    public function test_relative_vendor_directory_uses_the_project_root(): void
    {
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php return new \\Phel\\Config\\PhelConfig()->withSrcDirs(['src'])->withVendorDir('deps');",
        );
        foreach (['' => 'project-vendor', '/sub' => 'cwd-vendor'] as $prefix => $value) {
            $package = $this->projectDir . $prefix . '/deps/acme/example';
            mkdir($package . '/src/library', 0755, true);
            file_put_contents($package . '/phel-config.php', "<?php return ['src-dirs' => ['src']];");
            file_put_contents($package . '/src/library/sample.phel', '(ns library.sample) (def marker "' . $value . '")');
        }

        mkdir($this->projectDir . '/src/app');
        file_put_contents(
            $this->projectDir . '/src/app/main.phel',
            '(ns app.main (:require library.sample)) (println library.sample/marker)',
        );

        $result = $this->runPhelInDir($this->projectDir . '/sub', ['run', 'app.main']);

        self::assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        self::assertSame('project-vendor', trim($result['stdout']));
    }

    /**
     * @param list<string> $args
     *
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runPhelInDir(string $cwd, array $args): array
    {
        $env = getenv();
        $env['PHEL_CACHE_DIR'] = $this->projectDir . '/cache';
        $env['PHEL_NO_OPCACHE_REEXEC'] = '1';
        $process = Subprocess::run([PHP_BINARY, $this->binPath, ...$args], $cwd, env: $env);

        return ['exit' => $process->exitCode, 'stdout' => $process->stdout, 'stderr' => $process->stderr];
    }
}
