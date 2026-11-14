<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Evaluator;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Compiler\Domain\Evaluator\RequireEvaluator;
use Phel\Config\PhelConfig;
use Phel\Filesystem\FilesystemFacade;
use Phel\Filesystem\Infrastructure\RealFilesystem;
use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function defined;
use function dirname;
use function function_exists;
use function is_resource;
use function sprintf;

final class RequireEvaluatorTest extends TestCase
{
    use RemoveDirTrait;

    private RequireEvaluator $evaluator;

    private FilesystemFacade $filesystem;

    private string $tempDir = '';

    protected function setUp(): void
    {
        RealFilesystem::reset();
        Gacela::bootstrap(__DIR__);
        $this->filesystem = new FilesystemFacade();
        $this->evaluator = new RequireEvaluator($this->filesystem);
        RequireEvaluator::clearCache();
    }

    protected function tearDown(): void
    {
        $this->filesystem->clearAll();

        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }
    }

    public function test_it_creates_missing_temp_directory(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-test-' . uniqid('', true);
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, $this->tempDir);
        });

        $result = $this->evaluator->eval('return 42;');

        self::assertSame(42, $result);
        self::assertDirectoryExists($this->tempDir);
    }

    /**
     * The temp dir is shared by every process. Another process's empty or
     * half-written file for the same code must not be what this one requires
     * (#3495).
     */
    public function test_the_file_it_requires_belongs_to_this_process(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, $this->tempDir);
        });

        $hash = md5("<?php\nreturn 42;");
        $sharedName = sprintf('%s/__phel_%s.php', $this->tempDir, $hash);
        $samePidOtherProcess = sprintf('%s/__phel_%d_00000000_%s.php', $this->tempDir, getmypid(), $hash);
        file_put_contents($sharedName, '');
        file_put_contents($samePidOtherProcess, '');

        self::assertSame(42, $this->evaluator->eval('return 42;'));
        self::assertSame('', file_get_contents($sharedName), 'the pre-#3495 shared name is not used');
        self::assertSame('', file_get_contents($samePidOtherProcess), 'a process with the same pid in another container keeps its file');
    }

    public function test_a_php_host_removes_its_evaluated_files_on_exit(): void
    {
        $this->createTempDirectory();

        $result = $this->runOwner();

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertFileDoesNotExist($result->stdout);
        self::assertDirectoryDoesNotExist(dirname($result->stdout));
    }

    public function test_the_next_process_reclaims_a_killed_owners_files(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Requires a process killed without running PHP shutdown callbacks.');
        }

        $this->createTempDirectory();
        [$process, $pipes, $filename] = $this->startOwner();

        try {
            self::assertFileExists($filename);
            proc_terminate($process, 9);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            proc_close($process);
            $process = null;

            self::assertFileExists($filename);
            $next = $this->runOwner();

            self::assertSame(0, $next->exitCode, $next->stderr);
            self::assertFileDoesNotExist($filename);
            self::assertDirectoryDoesNotExist(dirname($filename));
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
        }
    }

    public function test_a_live_owners_files_survive_another_process_sweep(): void
    {
        $this->createTempDirectory();
        [$process, $pipes, $filename] = $this->startOwner();

        try {
            $next = $this->runOwner();

            self::assertSame(0, $next->exitCode, $next->stderr);
            self::assertFileExists($filename);
        } finally {
            fwrite($pipes[0], "exit\n");
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            self::assertSame(0, proc_close($process));
        }

        self::assertFileDoesNotExist($filename);
    }

    public function test_kept_debug_files_survive_exit_and_later_sweeps(): void
    {
        $this->createTempDirectory();
        $kept = $this->runOwner('keep');
        self::assertSame(0, $kept->exitCode, $kept->stderr);
        self::assertFileExists($kept->stdout);

        $next = $this->runOwner();

        self::assertSame(0, $next->exitCode, $next->stderr);
        self::assertFileExists($kept->stdout);
    }

    public function test_a_stopped_owners_files_survive_another_process_sweep(): void
    {
        if (!function_exists('posix_kill') || !defined('SIGSTOP') || !defined('SIGCONT')) {
            self::markTestSkipped('Requires POSIX stop and continue signals.');
        }

        $this->createTempDirectory();
        [$process, $pipes, $filename] = $this->startOwner();
        $pid = proc_get_status($process)['pid'];

        try {
            self::assertTrue(posix_kill($pid, SIGSTOP));
            $next = $this->runOwner();

            self::assertSame(0, $next->exitCode, $next->stderr);
            self::assertFileExists($filename);
        } finally {
            posix_kill($pid, SIGCONT);
            fwrite($pipes[0], "exit\n");
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            self::assertSame(0, proc_close($process));
        }

        self::assertFileDoesNotExist($filename);
    }

    public function test_a_fork_child_cannot_remove_or_release_the_parents_files(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('Requires pcntl_fork.');
        }

        $this->createTempDirectory();
        $result = Subprocess::run([
            PHP_BINARY,
            '-r',
            $this->ownerBootstrap() . <<<'PHP'
            $filename = $evaluator->eval('return __FILE__;');
            $child = pcntl_fork();
            if ($child === 0) {
                $evaluator->eval('return __FILE__ . "child";');
                $filesystem->clearAll();
                exit(0);
            }
            if ($child === -1) {
                exit(2);
            }
            pcntl_waitpid($child, $status);
            echo $filename;
            if (!is_file($filename) || pcntl_wexitstatus($status) !== 0) {
                exit(3);
            }
            PHP,
            dirname(__DIR__, 5),
            $this->tempDir,
            'normal',
        ]);

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertFileDoesNotExist($result->stdout);
    }

    private function createTempDirectory(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-eval-owner-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0o700, true);
    }

    private function runOwner(string $mode = 'normal'): Subprocess
    {
        return Subprocess::run([
            PHP_BINARY,
            '-r',
            $this->ownerBootstrap() . 'echo $evaluator->eval("return __FILE__;");',
            dirname(__DIR__, 5),
            $this->tempDir,
            $mode,
        ]);
    }

    /**
     * @return array{resource, array<int, resource>, string}
     */
    private function startOwner(): array
    {
        $process = proc_open([
            PHP_BINARY,
            '-r',
            $this->ownerBootstrap() . 'echo $evaluator->eval("return __FILE__ ;"), PHP_EOL; fflush(STDOUT); fgets(STDIN);',
            dirname(__DIR__, 5),
            $this->tempDir,
            'normal',
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 10);
        $filename = fgets($pipes[1]);
        self::assertIsString($filename);

        return [$process, $pipes, rtrim($filename)];
    }

    private function ownerBootstrap(): string
    {
        return <<<'PHP'
        require $argv[1] . '/vendor/autoload.php';
        \Gacela\Framework\Gacela::bootstrap($argv[1], static function (\Gacela\Framework\Bootstrap\GacelaConfig $config) use ($argv): void {
            $config->setFileCache(false);
            $config->setAppModulePaths(['src/php']);
            $config->addAppConfigKeyValue(\Phel\Config\PhelConfig::TEMP_DIR, $argv[2]);
            $config->addAppConfigKeyValue(\Phel\Config\PhelConfig::KEEP_GENERATED_TEMP_FILES, $argv[3] === 'keep');
        });
        $filesystem = new \Phel\Filesystem\FilesystemFacade();
        $evaluator = new \Phel\Compiler\Domain\Evaluator\RequireEvaluator($filesystem);
        PHP;
    }
}
