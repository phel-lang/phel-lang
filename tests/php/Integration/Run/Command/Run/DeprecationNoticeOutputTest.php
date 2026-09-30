<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function explode;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function realpath;
use function sprintf;
use function sys_get_temp_dir;
use function trim;

/**
 * Drives the real `bin/phel` as a subprocess and pins what a user sees on
 * stderr, because the noise this fixes was produced by PHP rather than by
 * Phel: `trigger_error()` rendered every notice twice and stamped the
 * `@internal` class that raised it onto each line (#3262).
 */
final class DeprecationNoticeOutputTest extends TestCase
{
    use RemoveDirTrait;

    private string $repoRoot;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 6);
        $this->projectDir = sys_get_temp_dir() . '/phel-deprecation-notice-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir . '/src', 0o755, true);

        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );

        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\n\ndeclare(strict_types=1);\n\n"
            . "use Phel\\Config\\PhelConfig;\n\n"
            . "return new PhelConfig()->withSrcDirs(['src'])->withVendorDir('');\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function test_one_deprecated_separator_prints_one_notice_naming_the_users_file(): void
    {
        $this->writeSeparatorNs();

        [$exitCode, $stdout, $stderr] = $this->runPhel(['run', 'src/main.phel']);
        [, , $warmStderr] = $this->runPhel(['run', 'src/main.phel']);

        self::assertSame(0, $exitCode, $stderr);
        self::assertSame("hi\n", $stdout);
        // The path is the one the user typed, openable from where they ran.
        $expected = "deprecated: Backslash ('\\') namespace separator in symbol 'dep\\main' at src/main.phel:1;"
            . " use dot ('.') instead, e.g. 'dep.main'."
            . ' The backslash form will be removed in a future release.';
        self::assertSame($expected, trim($stderr));
        self::assertSame($expected, trim($warmStderr));
    }

    public function test_the_notice_names_no_phel_internal_file_and_no_synthetic_source(): void
    {
        $this->writeSeparatorNs();

        [, , $stderr] = $this->runPhel(['run', 'src/main.phel']);

        self::assertStringStartsWith('deprecated: ', trim($stderr));
        self::assertCount(1, explode("\n", trim($stderr)));
        self::assertStringNotContainsString('ErrorNotice.php', $stderr);
        self::assertStringNotContainsString('string:1', $stderr);
    }

    public function test_an_opt_in_deprecation_stays_silent_without_the_flag(): void
    {
        $this->writeSuperseded();

        [$exitCode, $stdout, $stderr] = $this->runPhel(['run', 'src/superseded.phel']);

        self::assertSame(0, $exitCode, $stderr);
        self::assertSame("hi\n", $stdout);
        self::assertSame('', trim($stderr));
    }

    public function test_an_opt_in_deprecation_prints_one_notice_with_the_flag(): void
    {
        $this->writeSuperseded();

        [$exitCode, , $stderr] = $this->runPhel(['run', '--warn-deprecations', 'src/superseded.phel']);

        self::assertSame(0, $exitCode, $stderr);
        self::assertCount(1, explode("\n", trim($stderr)));
        self::assertStringStartsWith("deprecated: Definition 'phel.core/to-php-array'", trim($stderr));
        self::assertStringContainsString(' used at src/superseded.phel:2 ', $stderr);
    }

    public function test_a_file_the_run_does_not_load_reports_nothing_cold_or_warm(): void
    {
        $this->writeSource('main.phel', "(ns dep.main)\n(println \"hi\")\n");
        $this->writeSource('unloaded.phel', "(ns dep\\unloaded)\n");

        [$coldExit, $coldStdout, $coldStderr] = $this->runPhel(['run', 'src/main.phel']);
        [, , $warmStderr] = $this->runPhel(['run', 'src/main.phel']);

        self::assertSame(0, $coldExit, $coldStderr);
        self::assertSame("hi\n", $coldStdout);
        self::assertSame('', trim($coldStderr));
        self::assertSame('', trim($warmStderr));
        self::assertSame([], $this->cachedDeprecationsOf('main.phel'));
    }

    public function test_a_loaded_file_reports_its_own_notice_cold_and_warm(): void
    {
        $this->writeSource('main.phel', "(ns dep.main\n  (:require dep.lib))\n(println \"hi\")\n");
        $this->writeSource('lib.phel', "(ns dep\\lib)\n");
        $this->writeSource('unloaded.phel', "(ns dep\\unloaded)\n");

        [$coldExit, , $coldStderr] = $this->runPhel(['run', 'src/main.phel']);
        [, , $warmStderr] = $this->runPhel(['run', 'src/main.phel']);

        self::assertSame(0, $coldExit, $coldStderr);
        foreach ([$coldStderr, $warmStderr] as $stderr) {
            self::assertCount(1, explode("\n", trim($stderr)), $stderr);
            self::assertStringContainsString("symbol 'dep\\lib'", $stderr);
        }

        self::assertSame([], $this->cachedDeprecationsOf('main.phel'));
        self::assertCount(1, $this->cachedDeprecationsOf('lib.phel'));
    }

    private function writeSeparatorNs(): void
    {
        $this->writeSource('main.phel', "(ns dep\\main)\n(println \"hi\")\n");
    }

    /**
     * A deprecated *definition*, which is what the opt-in channel still carries:
     * the four superseded syntax forms became hard errors at 1.0.0 (#2877).
     */
    private function writeSuperseded(): void
    {
        $this->writeSource('superseded.phel', "(ns dep.superseded)\n(to-php-array [1 2])\n(println \"hi\")\n");
    }

    private function writeSource(string $name, string $code): void
    {
        file_put_contents($this->projectDir . '/src/' . $name, $code);
    }

    /**
     * @return list<array{message: string, announced: bool}>
     */
    private function cachedDeprecationsOf(string $name): array
    {
        /** @var array{entries: array<string, array{deprecations?: list<array{message: string, announced: bool}>}>} $index */
        $index = require $this->projectDir . '/.phel/cache/compiled-index.php';
        $source = (string) realpath($this->projectDir . '/src/' . $name);

        self::assertArrayHasKey($source, $index['entries']);

        return $index['entries'][$source]['deprecations'] ?? [];
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string, 2: string} exit code, stdout and stderr
     */
    private function runPhel(array $arguments): array
    {
        $args = '';
        foreach ($arguments as $argument) {
            $args .= ' ' . escapeshellarg($argument);
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && php -d memory_limit=256M ' . escapeshellarg($this->repoRoot . '/bin/phel') . $args;

        $process = Subprocess::run($cmd);

        return [$process->exitCode, $process->stdout, $process->stderr];
    }
}
