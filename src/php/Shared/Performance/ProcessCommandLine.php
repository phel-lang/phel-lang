<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

use function count;
use function function_exists;
use function is_array;
use function is_resource;

/**
 * Reads the full command line of the *running* process, interpreter flags
 * included.
 *
 * PHP drops everything before the script from `$_SERVER['argv']`: a process
 * started as `php -d display_errors=stderr bin/phel run x.phel` only ever sees
 * `['bin/phel', 'run', 'x.phel']`. The `-d`/`-n`/`-c` flags are therefore
 * unrecoverable from inside PHP, and any process the CLI replaces itself with
 * would silently run under the default ini. Asking the OS for the real argv is
 * the only way to carry them over.
 *
 * Best-effort by design: an empty list means "unknown", and every caller must
 * degrade to today's behaviour rather than guess.
 */
final class ProcessCommandLine
{
    private const string PROC_SELF_CMDLINE = '/proc/self/cmdline';

    /**
     * @return list<string> the process argv (binary first), or `[]` when unknown
     */
    public static function current(): array
    {
        $fromProc = self::fromProcFilesystem();
        if ($fromProc !== []) {
            return $fromProc;
        }

        return self::fromPs();
    }

    /**
     * Linux and friends: exact, NUL-separated, and free (a single file read).
     *
     * @return list<string>
     */
    private static function fromProcFilesystem(): array
    {
        if (!is_readable(self::PROC_SELF_CMDLINE)) {
            return [];
        }

        $raw = @file_get_contents(self::PROC_SELF_CMDLINE);
        if ($raw === false || $raw === '') {
            return [];
        }

        $args = explode("\0", $raw);
        // The buffer is NUL-terminated, so the split leaves one trailing empty
        // entry. Only that last one is padding: an empty arg in the middle is a
        // real (quoted-empty) argument.
        if ($args[count($args) - 1] === '') {
            array_pop($args);
        }

        return $args;
    }

    /**
     * macOS/BSD have no procfs. `ps` is the portable fallback, at the cost of
     * one fork (~3ms) and of losing argument quoting: the columns come back
     * space-joined, so an argument containing a space is indistinguishable from
     * two arguments. Callers cross-check the result against `$_SERVER['argv']`
     * and bail when it does not line up, which turns that ambiguity into a
     * clean "unknown" instead of a wrong reconstruction.
     *
     * @return list<string>
     */
    private static function fromPs(): array
    {
        if (DIRECTORY_SEPARATOR !== '/' || !function_exists('proc_open')) {
            return [];
        }

        $pid = getmypid();
        if ($pid === false) {
            return [];
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        // Array form: executed directly, never through a shell.
        $process = @proc_open(['ps', '-ww', '-o', 'args=', '-p', (string) $pid], $descriptors, $pipes);
        if (!is_resource($process)) {
            return [];
        }

        $stdout = stream_get_contents($pipes[1]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0 || $stdout === false) {
            return [];
        }

        $line = trim($stdout);
        if ($line === '') {
            return [];
        }

        $args = preg_split('/\s+/', $line);

        return is_array($args) ? $args : [];
    }
}
