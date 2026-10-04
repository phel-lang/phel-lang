<?php

declare(strict_types=1);

namespace PhelTest\Integration\Console;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function realpath;
use function sys_get_temp_dir;
use function unlink;

use const PHP_BINARY;

/**
 * `phel run` options belong to the command until the script path, and every
 * argument after the path belongs to the script (#3537).
 */
final class RunArgvOptionsTest extends TestCase
{
    private string $dir;

    private string $script;

    protected function setUp(): void
    {
        $this->dir = realpath(sys_get_temp_dir()) . '/phel-run-argv-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0755, true);

        $this->script = $this->dir . '/app.phel';
        file_put_contents($this->script, "(ns app-argv)\n(println (apply str (interpose \",\" *argv*)))\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->script);
        if (is_dir($this->dir)) {
            @rmdir($this->dir);
        }
    }

    public function test_run_options_before_the_path_are_not_read_as_the_path(): void
    {
        $result = $this->phelRun(['--stack-trace', '--debug', $this->script, 'one']);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        self::assertStringContainsString('one', $result->stdout);
        self::assertStringNotContainsString('--debug', $result->stdout);
    }

    public function test_options_after_the_path_reach_the_script(): void
    {
        $result = $this->phelRun([$this->script, '--warn-deprecations', '--debug', '--stack-trace']);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        self::assertStringContainsString('--warn-deprecations,--debug,--stack-trace', $result->stdout);
    }

    public function test_the_run_alias_reads_options_before_the_path(): void
    {
        $result = $this->phelRun(['--stack-trace', '--debug', $this->script, 'one'], 'r');

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        self::assertStringContainsString('one', $result->stdout);
        self::assertStringNotContainsString('--debug', $result->stdout);
    }

    public function test_application_options_before_the_path_are_not_read_as_the_path(): void
    {
        $result = $this->phelRun(['-v', $this->script, 'one']);

        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
        self::assertStringContainsString('one', $result->stdout);
        self::assertStringNotContainsString('-v', $result->stdout);
    }

    /**
     * @param list<string> $args
     */
    private function phelRun(array $args, string $command = 'run'): Subprocess
    {
        $bin = dirname(__DIR__, 4) . '/bin/phel';

        return Subprocess::run(
            [PHP_BINARY, $bin, $command, ...$args],
            cwd: $this->dir,
        );
    }
}
