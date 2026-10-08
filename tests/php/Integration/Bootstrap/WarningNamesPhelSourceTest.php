<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function getenv;
use function mkdir;
use function realpath;
use function sys_get_temp_dir;
use function uniqid;

use const PHP_BINARY;

/**
 * A PHP warning raised by compiled Phel code names the `.phel` file and line,
 * not the eval temp file (#3537).
 */
final class WarningNamesPhelSourceTest extends TestCase
{
    use RemoveDirTrait;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = realpath(sys_get_temp_dir()) . '/phel-warning-source-' . uniqid();
        mkdir($this->projectDir . '/src', 0777, true);
        file_put_contents(
            $this->projectDir . '/src/main.phel',
            "(ns app.main)\n\n(defn stamp []\n  (.setTimestamp (DateTimeImmutable.) 1)\n  :done)\n\n(println (stamp))\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function test_a_no_discard_warning_names_the_phel_file_and_line(): void
    {
        $process = $this->runMain();

        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
        self::assertStringContainsString(':done', $process->stdout);
        self::assertStringContainsString(
            'DateTimeImmutable::setTimestamp() does not modify the object itself in ' . $this->projectDir . '/src/main.phel on line 4',
            $process->stderr,
        );
        self::assertStringNotContainsString('__phel', $process->stderr);
    }

    public function test_the_reported_warning_stays_readable_through_error_get_last(): void
    {
        file_put_contents(
            $this->projectDir . '/src/main.phel',
            "(ns app.main)\n\n(php/file_get_contents \"/nonexistent-phel-file\")\n(println \"last:\" (get (php/error_get_last) \"message\"))\n",
        );

        $process = $this->runMain();

        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
        self::assertStringContainsString('last: file_get_contents(/nonexistent-phel-file): Failed to open stream', $process->stdout);
        self::assertStringContainsString('src/main.phel on line 3', $process->stderr);
    }

    private function runMain(): Subprocess
    {
        return Subprocess::run(
            [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'log_errors=0', dirname(__DIR__, 4) . '/bin/phel', 'run', 'src/main.phel'],
            $this->projectDir,
            env: [
                'PHEL_NO_OPCACHE_REEXEC' => '1',
                'PATH' => (string) getenv('PATH'),
                'HOME' => (string) getenv('HOME'),
                'TMPDIR' => (string) (getenv('TMPDIR') ?: '/tmp'),
            ],
        );
    }
}
