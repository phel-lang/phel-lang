<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Eval;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function getenv;
use function mkdir;
use function preg_replace;
use function sys_get_temp_dir;
use function uniqid;

use const PHP_BINARY;

/**
 * `phel eval` scans the working directory, so the case needs a child process
 * whose cwd holds nothing but an unrelated file (#3484).
 */
final class EvalOutsideProjectSubprocessTest extends TestCase
{
    use RemoveDirTrait;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phel-eval-outside-' . uniqid();
        mkdir($this->dir . '/sub', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function test_an_unrelated_file_without_an_ns_form_does_not_stop_eval(): void
    {
        file_put_contents($this->dir . '/sub/stray.cljc', "(defn x [] 1)\n");

        $process = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 6) . '/bin/phel', 'eval', '(+ 1 2)'],
            $this->dir,
            env: [
                'PHEL_NO_OPCACHE_REEXEC' => '1',
                'PATH' => (string) getenv('PATH'),
                'HOME' => (string) getenv('HOME'),
                'TMPDIR' => (string) (getenv('TMPDIR') ?: '/tmp'),
            ],
        );

        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
        self::assertSame('3', trim((string) preg_replace('/\e\[[0-9;]*m/', '', $process->stdout)));
    }
}
