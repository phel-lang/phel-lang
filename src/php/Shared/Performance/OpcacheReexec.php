<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

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

    public static function decide(
        bool $opcacheLoaded,
        bool $fileCacheConfigured,
        bool $optedOut,
        bool $pcntlAvailable,
        string $fileCacheDir,
        bool $reexecAlreadyDone = false,
    ): OpcacheReexecDecision {
        // file_cache already set is one loop guard (the child inherits the
        // flag); the breadcrumb is the belt-and-suspenders one that holds even
        // if a build fails to read that flag back.
        if ($optedOut || !$pcntlAvailable || $fileCacheConfigured || $reexecAlreadyDone) {
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
}
