<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;

final class StartupDirectoryTest extends TestCase
{
    use RemoveDirTrait;

    private string $root;

    protected function setUp(): void
    {
        $this->root = realpath(sys_get_temp_dir()) . '/phel-startup-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    #[DataProvider('informationalCommands')]
    public function test_informational_commands_leave_a_writable_empty_directory_unchanged(string $argument): void
    {
        $before = scandir($this->root);
        $process = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', $argument],
            $this->root,
            env: $this->environment(['PHEL_OPCACHE_REEXEC' => '1']),
        );

        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
        self::assertNotSame('', $process->stdout);
        self::assertSame($before, scandir($this->root));
    }

    public static function informationalCommands(): iterable
    {
        yield 'version' => ['--version'];
        yield 'help option' => ['--help'];
        yield 'help command' => ['help'];
        yield 'list command' => ['list'];
    }

    #[DataProvider('runModes')]
    public function test_run_seeds_project_state_and_only_actual_reexec_creates_opcache(array $overrides): void
    {
        file_put_contents($this->root . '/phel-config.php', "<?php return new \\Phel\\Config\\PhelConfig()->withSrcDirs(['.'])->withVendorDir('');");
        file_put_contents($this->root . '/main.phel', <<<'PHEL_WRAP'
        (ns startup.main)
        (println (str (php/aget *argv* 0) "," (php/aget *argv* 1)))
        (println "reexec" (php/getenv "PHEL_OPCACHE_REEXEC_DONE"))
        PHEL_WRAP);
        $process = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', 'run', 'main.phel', '--', '--version'],
            $this->root,
            env: $this->environment($overrides),
        );

        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
        self::assertStringStartsWith("--,--version\n", $process->stdout);
        self::assertFileExists($this->root . '/.phel/.gitignore');
        self::assertStringContainsString("\n*\n", (string) file_get_contents($this->root . '/.phel/.gitignore'));
        $actuallyReexeced = !isset($overrides['PHEL_OPCACHE_REEXEC_DONE']) && str_contains($process->stdout, 'reexec 1');
        if ($actuallyReexeced) {
            self::assertDirectoryExists($this->root . '/.phel/opcache');
        } else {
            self::assertDirectoryDoesNotExist($this->root . '/.phel/opcache');
        }
    }

    public static function runModes(): iterable
    {
        yield 'opted out' => [['PHEL_NO_OPCACHE_REEXEC' => '1']];
        yield 'inherited reexec breadcrumb' => [['PHEL_OPCACHE_REEXEC_DONE' => '1', 'PHEL_OPCACHE_REEXEC' => '1']];
        yield 'platform defaults' => [[]];
        yield 'opted in' => [['PHEL_OPCACHE_REEXEC' => '1']];
    }

    #[DataProvider('invalidFlags')]
    public function test_informational_commands_still_validate_environment_flags(string $flag): void
    {
        $process = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', '--version'],
            $this->root,
            env: $this->environment([$flag => 'invalid']),
        );

        self::assertSame(2, $process->exitCode);
        self::assertStringContainsString($flag . ' must be one of', $process->stderr);
        self::assertSame(['.', '..'], scandir($this->root));
    }

    public static function invalidFlags(): iterable
    {
        yield 'opt out' => ['PHEL_NO_OPCACHE_REEXEC'];
        yield 'opt in' => ['PHEL_OPCACHE_REEXEC'];
    }

    private function environment(array $overrides = []): array
    {
        $environment = getenv();
        foreach (['PHEL_DIR', 'PHEL_CACHE_DIR', 'PHEL_NO_OPCACHE_REEXEC', 'PHEL_OPCACHE_REEXEC', 'PHEL_OPCACHE_REEXEC_DONE'] as $name) {
            unset($environment[$name]);
        }

        return array_replace($environment, $overrides);
    }
}
