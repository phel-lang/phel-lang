<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Performance;

use Phel\Shared\Performance\OpcacheWorkerFlags;
use PHPUnit\Framework\TestCase;

final class OpcacheWorkerFlagsTest extends TestCase
{
    public function test_returns_enable_cli_and_file_cache_flags_when_opcache_is_loaded(): void
    {
        $flags = OpcacheWorkerFlags::forFileCache(true, '/var/phel/opcache-workers');

        self::assertSame(
            ['-d', 'opcache.enable_cli=1', '-d', 'opcache.file_cache=/var/phel/opcache-workers'],
            $flags,
        );
    }

    public function test_returns_no_flags_when_opcache_extension_is_absent(): void
    {
        self::assertSame([], OpcacheWorkerFlags::forFileCache(false, '/var/phel/opcache-workers'));
    }

    public function test_returns_no_flags_when_cache_dir_is_empty(): void
    {
        // No cache dir means the file_cache path would be empty, which makes
        // PHP abort at startup; degrade to plain workers instead.
        self::assertSame([], OpcacheWorkerFlags::forFileCache(true, ''));
    }
}
