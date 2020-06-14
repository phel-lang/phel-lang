<?php

declare(strict_types=1);

namespace Phel\Repl;

use PHPUnit\Framework\TestCase;

final class ColorStyleTest extends TestCase
{
    public function testCustomColor(): void
    {
        $color = 'custom';
        $format = 'begin %s end';
        $style = ColorStyle::withStyles([$color => $format]);
        $anyText = 'any text';

        $this->assertEquals(
            sprintf($format, $anyText),
            $style->color($anyText, $color)
        );
    }
}
