<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\SourceMap;

use Phel\Shared\SourceMap\SourceMapSiblings;
use PHPUnit\Framework\TestCase;

final class SourceMapSiblingsTest extends TestCase
{
    public function test_map_file_appends_map_suffix(): void
    {
        self::assertSame('/out/phel/json.php.map', SourceMapSiblings::mapFile('/out/phel/json.php'));
    }

    public function test_source_file_replaces_php_suffix(): void
    {
        self::assertSame('/out/phel/json.phel', SourceMapSiblings::sourceFile('/out/phel/json.php'));
    }

    public function test_source_file_only_touches_the_suffix(): void
    {
        self::assertSame('/out/x.php-dir/main.phel', SourceMapSiblings::sourceFile('/out/x.php-dir/main.php'));
    }

    public function test_source_file_appends_suffix_when_file_is_not_php(): void
    {
        self::assertSame('/out/main.txt.phel', SourceMapSiblings::sourceFile('/out/main.txt'));
    }
}
