<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use PHPUnit\Framework\TestCase;

final class CacheableCompilerFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        CacheableConfig::reset();
        Gacela::bootstrap(__DIR__);
    }

    protected function tearDown(): void
    {
        CacheableConfig::reset();
    }

    public function test_encode_ns_returns_consistent_result(): void
    {
        $facade = new CompilerFacade();

        $first = $facade->encodeNs('phel\\core');
        $second = $facade->encodeNs('phel\\core');

        self::assertSame($first, $second);
        self::assertSame('phel\core', $first);
    }

    public function test_encode_ns_caches_per_namespace(): void
    {
        $facade = new CompilerFacade();

        $core = $facade->encodeNs('phel\\core');
        $string = $facade->encodeNs('phel\\string');

        self::assertNotSame($core, $string);
        self::assertSame('phel\core', $core);
        self::assertSame('phel\string', $string);
    }

    public function test_clear_cache_allows_fresh_encode(): void
    {
        $facade = new CompilerFacade();

        $before = $facade->encodeNs('phel\\core');
        CompilerFacade::clearMethodCache();
        $after = $facade->encodeNs('phel\\core');

        self::assertSame($before, $after);
    }
}
