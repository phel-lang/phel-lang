<?php

declare(strict_types=1);

namespace PhelTest\Unit\Config;

use Phel\Config\ProjectLayout;
use PHPUnit\Framework\TestCase;

final class ProjectLayoutTest extends TestCase
{
    public function test_conventional_layout_directories(): void
    {
        $layout = ProjectLayout::Conventional;

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

    public function test_enum_values(): void
    {
        self::assertSame('conventional', ProjectLayout::Conventional->value);
        self::assertSame('flat', ProjectLayout::Flat->value);
    }
}
