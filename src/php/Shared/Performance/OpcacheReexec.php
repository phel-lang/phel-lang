<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

use function array_intersect;
use function array_slice;
use function in_array;
use function str_starts_with;

/**
 * Decides whether the `phel` CLI should re-exec itself with a persistent
 * OPcache file cache so warm `run`/`eval`/`repl` invocations reuse compiled
 * opcode instead of re-parsing every required `.php`.
 *
 * `opcache.enable_cli` (PHP_INI_SYSTEM) and `opcache.file_cache` are
 * startup-only — `ini_set()` cannot turn them on mid-process. The only way to
 * auto-apply them within one invocation is to replace the process image with
 * `pcntl_exec()`, which keeps the same PID, file descriptors (stdin/stdout/
 * stderr + TTY), and signal disposition, so the interactive REPL, exit codes,
 * and argv all survive transparently. A wrapping child (proc_open/passthru)
 * would not, so we degrade rather than re-exec when `pcntl_exec` is missing.
 *
 * Pure: callers pass the runtime facts so the decision stays trivially
 * testable; the actual exec lives at the CLI edge (`bin/phel`).
 */
final class OpcacheReexec
{
    /**
     * Set in the environment right before `pcntl_exec` so the re-exec'd child
     * proves it is the second invocation without reading back an ini value.
     * Its presence is an unconditional "never re-exec again", so a misread
     * `opcache.file_cache` on any PHP/opcache build can never spin an exec loop.
     */
    public const string REEXEC_DONE_ENV = 'PHEL_OPCACHE_REEXEC_DONE';

    /**
     * Commands that compile no Phel, so a warm opcode cache has nothing to
     * speed up and the second interpreter startup is pure cost.
     */
    private const array COMMANDS_WITHOUT_COMPILATION = [
        'list', 'help', 'completion', '_complete', 'config', 'init',
        'agent-install', 'cache:clear', 'explain', 'validate:config',
    ];

    /**
     * Commands that load many compiled files in one process, where the warm
     * opcode cache pays for the second startup even on macOS.
     */
    private const array COMMANDS_LOADING_MANY_FILES = [
        'test', 'build', 'bench', 'mutate', 'profile', 'export',
    ];

    /**
     * Whether the invocation `$argv` (script path first) loads many compiled
     * files: one of the commands above.
     *
     * @param list<string> $argv
     */
    public static function loadsManyFiles(array $argv): bool
    {
        return in_array(self::commandOf($argv), self::COMMANDS_LOADING_MANY_FILES, true);
    }

    /**
     * Whether the invocation `$argv` (script path first) compiles nothing:
     * `--version`, a bare `--help`, or a command from the list above. No
     * command at all starts the REPL, which compiles.
     *
     * @param list<string> $argv
     */
    public static function compilesNothing(array $argv): bool
    {
        $command = self::commandOf($argv);
        if ($command !== null) {
            return in_array($command, self::COMMANDS_WITHOUT_COMPILATION, true);
        }

        return array_intersect($argv, ['--version', '-V', '--help', '-h']) !== [];
    }

    public static function decide(
        bool $opcacheLoaded,
        bool $fileCacheConfigured,
        bool $optedOut,
        bool $pcntlAvailable,
        string $fileCacheDir,
        bool $reexecAlreadyDone = false,
        bool $compilesNothing = false,
        bool $isMacOs = false,
        bool $optedIn = false,
        bool $loadsManyFiles = false,
    ): OpcacheReexecDecision {
        // file_cache already set is one loop guard (the child inherits the
        // flag); the breadcrumb is the belt-and-suspenders one that holds even
        // if a build fails to read that flag back.
        if ($optedOut || !$pcntlAvailable || $fileCacheConfigured || $reexecAlreadyDone || $compilesNothing) {
            return new OpcacheReexecDecision(false, []);
        }

        // A second PHP startup costs about 37 ms on macOS against about 10 ms
        // on Linux, so there the re-exec loses 20-38 ms on `run`, `eval` and
        // the like, and wins only where many compiled files load (#3425). Opt
        // in with PHEL_OPCACHE_REEXEC=1, or set opcache.file_cache in php.ini
        // to get the cache with no exec.
        if ($isMacOs && !$optedIn && !$loadsManyFiles) {
            return new OpcacheReexecDecision(false, []);
        }

        $flags = OpcacheWorkerFlags::forFileCache($opcacheLoaded, $fileCacheDir);
        if ($flags === []) {
            return new OpcacheReexecDecision(false, []);
        }

        // file_cache_only skips OPcache's shared-memory segment (and its
        // /tmp/.ZendSem.* semaphore) while keeping the on-disk opcode cache that
        // gives the warm-start win. A short-lived CLI process never reuses SHM
        // across invocations, and allocating it can block on a semaphore lock
        // during startup on some CI filesystems — dropping it removes that hang
        // with no loss of cache benefit. Scoped to the CLI re-exec; parallel
        // test workers keep SHM.
        $flags[] = '-d';
        $flags[] = 'opcache.file_cache_only=1';

        return new OpcacheReexecDecision(true, $flags);
    }

    /**
     * The first argument that is not an option, or null for none.
     *
     * @param list<string> $argv
     */
    private static function commandOf(array $argv): ?string
    {
        foreach (array_slice($argv, 1) as $arg) {
            if (!str_starts_with($arg, '-')) {
                return $arg;
            }
        }

        return null;
    }
}
