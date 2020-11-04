<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Repl;

use Phel\Command\Repl\ColorStyle;
use PHPUnit\Framework\TestCase;

final class ColorStyleTest extends TestCase
{
    public function testCustomColor(): void
    {
        $color = 'custom';
        $format = 'begin %s end';
        $style = ColorStyle::withStyles([$color => $format]);
        $anyText = 'any text';

        self::assertEquals(
            sprintf($format, $anyText),
            $style->color($anyText, $color)
        );
    }

    public function testDefaultColors(): void
    {
        $style = ColorStyle::withStyles();
        $anyText = 'any text';

        self::assertSame('[0;32many text[0m', $style->green($anyText));
        self::assertSame('[31;31many text[0m', $style->red($anyText));
        self::assertSame('[33;33many text[0m', $style->yellow($anyText));
        self::assertSame('[33;34many text[0m', $style->blue($anyText));
    }
}
