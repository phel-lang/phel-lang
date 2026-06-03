<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile;

use Phel\Build\Domain\Compile\BuildOptions;
use PHPUnit\Framework\TestCase;

final class BuildOptionsTest extends TestCase
{
    public function test_cache_and_source_map_both_enabled(): void
    {
        $options = new BuildOptions(enableCache: true, enableSourceMap: true);

        self::assertTrue($options->isCacheEnabled());
        self::assertTrue($options->isSourceMapEnabled());
    }

    public function test_cache_and_source_map_both_disabled(): void
    {
        $options = new BuildOptions(enableCache: false, enableSourceMap: false);

        self::assertFalse($options->isCacheEnabled());
        self::assertFalse($options->isSourceMapEnabled());
    }

    public function test_flags_are_independent(): void
    {
        $cacheOnly = new BuildOptions(enableCache: true, enableSourceMap: false);

        self::assertTrue($cacheOnly->isCacheEnabled());
        self::assertFalse($cacheOnly->isSourceMapEnabled());

        $sourceMapOnly = new BuildOptions(enableCache: false, enableSourceMap: true);

        self::assertFalse($sourceMapOnly->isCacheEnabled());
        self::assertTrue($sourceMapOnly->isSourceMapEnabled());
    }
}
