<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\ColorStyle;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class ColorStyleTest extends TestCase
{
    public function test_custom_color(): void
    {
        $color = 'custom';
        $format = 'begin %s end';
        $style = ColorStyle::withStyles([$color => $format]);
        $anyText = 'any text';

        self::assertSame(
            sprintf($format, $anyText),
            $style->color($anyText, $color),
        );
    }

    public function test_default_colors(): void
    {
        $style = ColorStyle::withStyles();
        $anyText = 'any text';

        self::assertSame("\033[0;32many text\033[0m", $style->green($anyText));
        self::assertSame("\033[31;31many text\033[0m", $style->red($anyText));
        self::assertSame("\033[33;33many text\033[0m", $style->yellow($anyText));
        self::assertSame("\033[33;34many text\033[0m", $style->blue($anyText));
    }
}
