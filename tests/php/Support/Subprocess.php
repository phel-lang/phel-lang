<?php

declare(strict_types=1);

namespace PhelTest\Support;

use RuntimeException;

use function fclose;
use function feof;
use function fread;
use function fwrite;
use function implode;
use function is_array;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_select;
use function stream_set_blocking;

/**
 * Runs a child process and reads stdout and stderr together.
 *
 * Reading one pipe to the end before the other deadlocks as soon as the child
 * writes more than a pipe holds to the unread one (16 KB on macOS): the child
 * blocks on its write and never exits (#3378).
 */
final readonly class Subprocess
{
    private function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}

    /**
     * @param list<string>|string        $command a list runs without a shell
     * @param string|null                $stdin   null inherits the parent's stdin
     * @param array<string, string>|null $env     null inherits the parent's environment
     */
    public static function run(
        array|string $command,
        ?string $cwd = null,
        ?string $stdin = null,
        ?array $env = null,
    ): self {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        if ($stdin !== null) {
            $descriptors[0] = ['pipe', 'r'];
        }

        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('proc_open failed for: ' . (is_array($command) ? implode(' ', $command) : $command));
        }

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);
        }

        [$stdout, $stderr] = self::drain($pipes[1], $pipes[2]);

        return new self(proc_close($process), $stdout, $stderr);
    }

    /**
     * @param resource $stdoutPipe
     * @param resource $stderrPipe
     *
     * @return array{string, string}
     */
    private static function drain($stdoutPipe, $stderrPipe): array
    {
        $open = [1 => $stdoutPipe, 2 => $stderrPipe];
        $output = [1 => '', 2 => ''];
        foreach ($open as $pipe) {
            stream_set_blocking($pipe, false);
        }

        while ($open !== []) {
            $read = $open;
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, null) === false) {
                break;
            }

            foreach ($open as $fd => $pipe) {
                $chunk = fread($pipe, 65_536);
                if ($chunk !== false) {
                    $output[$fd] .= $chunk;
                }

                if (feof($pipe)) {
                    fclose($pipe);
                    unset($open[$fd]);
                }
            }
        }

        return [$output[1], $output[2]];
    }
}
