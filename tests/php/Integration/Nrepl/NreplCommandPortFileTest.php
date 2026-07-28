<?php

declare(strict_types=1);

namespace PhelTest\Integration\Nrepl;

use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function implode;
use function is_dir;
use function is_file;
use function is_resource;
use function microtime;
use function mkdir;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function random_bytes;
use function realpath;
use function rmdir;
use function scandir;
use function sprintf;
use function stream_socket_client;
use function sys_get_temp_dir;
use function trim;
use function unlink;
use function usleep;

use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;
use const SIGKILL;

/**
 * End-to-end for the `phel nrepl` port-file contract: the server writes a
 * Clojure-standard `.nrepl-port` file in the CWD once it is listening, and
 * the file is removed again when the server stops — here exercised through
 * SIGTERM, the way editors and process managers stop it.
 */
final class NreplCommandPortFileTest extends TestCase
{
    private string $binPath;

    private string $projectDir;

    /** @var resource|null */
    private mixed $process = null;

    protected function setUp(): void
    {
        $this->binPath = dirname(__DIR__, 4) . '/bin/phel';

        $this->projectDir = realpath(sys_get_temp_dir())
            . DIRECTORY_SEPARATOR . 'phel-nrepl-port-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0o755, true);
        file_put_contents(
            $this->projectDir . DIRECTORY_SEPARATOR . 'phel-config.php',
            "<?php\n\nreturn (new \\Phel\\Config\\PhelConfig());\n",
        );
    }

    protected function tearDown(): void
    {
        $this->killServer();

        @unlink($this->portFile());
        @unlink($this->projectDir . DIRECTORY_SEPARATOR . 'phel-config.php');
        @unlink($this->projectDir . DIRECTORY_SEPARATOR . 'server.log');
        @rmdir($this->projectDir);
    }

    public function test_writes_port_file_while_running_and_removes_it_on_sigterm(): void
    {
        if (!function_exists('pcntl_signal')) {
            self::markTestSkipped('ext-pcntl is required for signal-based shutdown.');
        }

        $logFile = $this->projectDir . DIRECTORY_SEPARATOR . 'server.log';
        $cmd = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->binPath),
            'nrepl',
            '--port=0',
        ]);

        $process = proc_open(
            $cmd,
            [
                1 => ['file', $logFile, 'a'],
                2 => ['file', $logFile, 'a'],
            ],
            $pipes,
            $this->projectDir,
        );
        self::assertIsResource($process, 'proc_open failed');
        $this->process = $process;

        $port = $this->waitForPortFile($process, $logFile);
        self::assertGreaterThan(0, $port);

        // The file must name the socket the server is actually listening on.
        $client = @stream_socket_client(
            sprintf('tcp://127.0.0.1:%d', $port),
            $errno,
            $errstr,
            5.0,
        );
        self::assertNotFalse($client, sprintf('Port %d from .nrepl-port refuses connections: %s', $port, $errstr));
        fclose($client);

        self::assertTrue(proc_terminate($process), 'could not SIGTERM the server');
        $exitCode = $this->waitForExit($process);
        $this->process = null;

        self::assertSame(
            0,
            $exitCode,
            'server should shut down gracefully; log: ' . @file_get_contents($logFile),
        );
        self::assertFileDoesNotExist($this->portFile());
    }

    private function portFile(): string
    {
        return $this->projectDir . DIRECTORY_SEPARATOR . '.nrepl-port';
    }

    /**
     * @param resource $process
     */
    private function waitForPortFile(mixed $process, string $logFile): int
    {
        $deadline = microtime(true) + 60.0;
        while (microtime(true) < $deadline) {
            if (is_file($this->portFile())) {
                $port = (int) trim((string) file_get_contents($this->portFile()));
                if ($port > 0) {
                    return $port;
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                self::fail(sprintf(
                    'nREPL server exited before writing .nrepl-port; log: %s',
                    (string) @file_get_contents($logFile),
                ));
            }

            usleep(100_000);
        }

        self::fail(sprintf(
            'Timed out waiting for .nrepl-port; log: %s',
            (string) @file_get_contents($logFile),
        ));
    }

    /**
     * @param resource $process
     */
    private function waitForExit(mixed $process): int
    {
        $deadline = microtime(true) + 15.0;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                // proc_get_status() reaps the child itself, so proc_close()
                // finds nothing left to wait for and answers -1. The real
                // exit code is only in this status array.
                $exitCode = $status['exitcode'];
                proc_close($process);

                return $exitCode;
            }

            usleep(100_000);
        }

        proc_terminate($process, SIGKILL);
        proc_close($process);

        self::fail('nREPL server did not exit within 15s of SIGTERM');
    }

    private function killServer(): void
    {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['running']) {
                proc_terminate($this->process, SIGKILL);
            }

            proc_close($this->process);
            $this->process = null;
        }

        // The server may have created a cache dir next to phel-config.php.
        $cacheDir = $this->projectDir . DIRECTORY_SEPARATOR . '.phel';
        if (is_dir($cacheDir)) {
            $this->removeTree($cacheDir);
        }
    }

    private function removeTree(string $dir): void
    {
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
