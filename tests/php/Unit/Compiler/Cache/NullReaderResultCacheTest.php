<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Cache;

use Phel\Compiler\Infrastructure\Cache\NullReaderResultCache;
use PHPUnit\Framework\TestCase;

final class NullReaderResultCacheTest extends TestCase
{
    public function test_load_always_misses(): void
    {
        $cache = new NullReaderResultCache();

        self::assertNull($cache->load('(+ 1 2)', 0));
    }

    public function test_save_is_a_noop_and_load_still_misses(): void
    {
        $cache = new NullReaderResultCache();

        $cache->save('(+ 1 2)', 0, []);

        self::assertNull($cache->load('(+ 1 2)', 0));
    }
}
