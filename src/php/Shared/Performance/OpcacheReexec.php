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
    public static function decide(
        bool $opcacheLoaded,
        bool $fileCacheConfigured,
        bool $optedOut,
        bool $pcntlAvailable,
        string $fileCacheDir,
    ): OpcacheReexecDecision {
        // Already configured is also the loop guard: the re-exec'd child
        // inherits the file_cache flag, so it falls through here and never
        // re-execs again.
        if ($optedOut || !$pcntlAvailable || $fileCacheConfigured) {
            return new OpcacheReexecDecision(false, []);
        }

        $flags = OpcacheWorkerFlags::forFileCache($opcacheLoaded, $fileCacheDir);
        if ($flags === []) {
            return new OpcacheReexecDecision(false, []);
        }

        return new OpcacheReexecDecision(true, $flags);
    }
}
