<?php

declare(strict_types=1);

namespace PhelTest\Unit\Config;

use Phel\Config\ProjectLayout;
use PHPUnit\Framework\TestCase;

final class ProjectLayoutTest extends TestCase
{
    public function test_nested_layout_directories(): void
    {
        $layout = ProjectLayout::Nested;

        self::assertSame('src/phel', $layout->getSrcDir());
        self::assertSame('tests/phel', $layout->getTestDir());
        self::assertSame(['src/phel', 'tests/phel'], $layout->getFormatDirs());
        self::assertSame(['src/phel'], $layout->getExportFromDirs());
    }

    public function test_flat_layout_directories(): void
    {
        $layout = ProjectLayout::Flat;

        self::assertSame('src', $layout->getSrcDir());
        self::assertSame('tests', $layout->getTestDir());
        self::assertSame(['src', 'tests'], $layout->getFormatDirs());
        self::assertSame(['src'], $layout->getExportFromDirs());
    }

    public function test_root_layout_directories(): void
    {
        $layout = ProjectLayout::Root;

        self::assertSame('.', $layout->getSrcDir());
        self::assertSame('.', $layout->getTestDir());
        self::assertSame(['.'], $layout->getFormatDirs());
        self::assertSame(['.'], $layout->getExportFromDirs());
    }

    public function test_enum_values(): void
    {
        self::assertSame('nested', ProjectLayout::Nested->value);
        self::assertSame('flat', ProjectLayout::Flat->value);
        self::assertSame('root', ProjectLayout::Root->value);
    }
}
