<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

/**
 * Outcome of {@see OpcacheReexec::decide()}: whether the CLI should replace its
 * own process image to gain a persistent OPcache file cache, and the `-d` flags
 * to apply when it does.
 */
final readonly class OpcacheReexecDecision
{
    /**
     * @param list<string> $flags
     */
    public function __construct(
        public bool $shouldReexec,
        public array $flags,
    ) {}

    /**
     * Splices the ini flags the user typed on the original command line in
     * front of the ones Phel needs, so `php -d display_errors=stderr bin/phel …`
     * still means what it says after the process replaces itself.
     *
     * Order is the whole point: PHP applies `-d` left to right and the last
     * occurrence wins, so putting Phel's flags last makes them authoritative on
     * the three directives it must control, while every other user override
     * survives untouched. Phel forces exactly:
     *
     *  - `opcache.enable_cli=1`   — OPcache is inert on CLI without it, so the
     *                               re-exec would cost a process start and buy
     *                               nothing.
     *  - `opcache.file_cache=<dir>` — the reason for the re-exec, and the flag
     *                               the child reads back as its loop guard.
     *  - `opcache.file_cache_only=1` — skips the SHM segment and the startup
     *                               semaphore that can block on some CI hosts.
     *
     * Users who want their own OPcache settings honoured instead opt out of the
     * re-exec entirely with `PHEL_NO_OPCACHE_REEXEC=1`.
     *
     * @param list<string> $userIniFlags
     */
    public function withUserIniFlags(array $userIniFlags): self
    {
        if ($userIniFlags === []) {
            return $this;
        }

        return new self($this->shouldReexec, [...$userIniFlags, ...$this->flags]);
    }
}
