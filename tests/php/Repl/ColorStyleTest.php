<?php

declare(strict_types=1);

namespace Phel\Repl;

use PHPUnit\Framework\TestCase;

final class ColorStyleTest extends TestCase
{
    public function testColors(): void
    {
        $colorStyle = ColorStyle::withDefaultStyles();
        $any = 'any text';

        foreach (ColorStyle::DEFAULT_STYLES as $color => $format) {
            $this->assertEquals(
                sprintf($format, $any),
                $colorStyle->color($any, $color)
            );
        }
    }
}
