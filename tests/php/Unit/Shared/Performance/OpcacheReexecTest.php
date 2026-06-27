<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Performance;

use Phel\Shared\Performance\OpcacheReexec;
use PHPUnit\Framework\TestCase;

final class OpcacheReexecTest extends TestCase
{
    public function test_decides_to_reexec_with_file_cache_flags_when_everything_is_available(): void
    {
        $decision = OpcacheReexec::decide(
            opcacheLoaded: true,
            fileCacheConfigured: false,
            optedOut: false,
            pcntlAvailable: true,
            fileCacheDir: '/var/phel/opcache',
        );

        self::assertTrue($decision->shouldReexec);
        self::assertSame(
            ['-d', 'opcache.enable_cli=1', '-d', 'opcache.file_cache=/var/phel/opcache'],
            $decision->flags,
        );
    }

    public function test_does_not_reexec_when_opcache_extension_is_absent(): void
    {
        $decision = OpcacheReexec::decide(
            opcacheLoaded: false,
            fileCacheConfigured: false,
            optedOut: false,
            pcntlAvailable: true,
            fileCacheDir: '/var/phel/opcache',
        );

        self::assertFalse($decision->shouldReexec);
        self::assertSame([], $decision->flags);
    }

    public function test_does_not_reexec_when_file_cache_is_already_configured(): void
    {
        // The re-exec'd child inherits the file_cache flag, so this is also the
        // guard that prevents an infinite re-exec loop.
        $decision = OpcacheReexec::decide(
            opcacheLoaded: true,
            fileCacheConfigured: true,
            optedOut: false,
            pcntlAvailable: true,
            fileCacheDir: '/var/phel/opcache',
        );

        self::assertFalse($decision->shouldReexec);
        self::assertSame([], $decision->flags);
    }

    public function test_does_not_reexec_when_opted_out(): void
    {
        $decision = OpcacheReexec::decide(
            opcacheLoaded: true,
            fileCacheConfigured: false,
            optedOut: true,
            pcntlAvailable: true,
            fileCacheDir: '/var/phel/opcache',
        );

        self::assertFalse($decision->shouldReexec);
        self::assertSame([], $decision->flags);
    }

    public function test_does_not_reexec_when_pcntl_is_unavailable(): void
    {
        // Without pcntl_exec there is no in-place process replacement, and a
        // wrapping child would risk TTY/stdin/exit-code fidelity, so degrade.
        $decision = OpcacheReexec::decide(
            opcacheLoaded: true,
            fileCacheConfigured: false,
            optedOut: false,
            pcntlAvailable: false,
            fileCacheDir: '/var/phel/opcache',
        );

        self::assertFalse($decision->shouldReexec);
        self::assertSame([], $decision->flags);
    }

    public function test_does_not_reexec_when_cache_dir_is_empty(): void
    {
        $decision = OpcacheReexec::decide(
            opcacheLoaded: true,
            fileCacheConfigured: false,
            optedOut: false,
            pcntlAvailable: true,
            fileCacheDir: '',
        );

        self::assertFalse($decision->shouldReexec);
        self::assertSame([], $decision->flags);
    }
}
