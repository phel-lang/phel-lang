<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\NoColor;
use PHPUnit\Framework\TestCase;

final class NoColorTest extends TestCase
{
    public function test_absent_variable_keeps_colour(): void
    {
        self::assertFalse(NoColor::isRequested([]));
    }

    public function test_empty_value_keeps_colour(): void
    {
        // <https://no-color.org>: the variable counts only when non-empty.
        self::assertFalse(NoColor::isRequested(['NO_COLOR' => '']));
    }

    public function test_any_non_empty_value_requests_plain_output(): void
    {
        self::assertTrue(NoColor::isRequested(['NO_COLOR' => '1']));
        self::assertTrue(NoColor::isRequested(['NO_COLOR' => '0']), 'the value is not read, only its presence');
        self::assertTrue(NoColor::isRequested(['NO_COLOR' => 'false']));
    }

    public function test_style_is_plain_when_requested(): void
    {
        self::assertSame('x', NoColor::style(['NO_COLOR' => '1'])->red('x'));
    }

    public function test_style_carries_colour_by_default(): void
    {
        self::assertNotSame('x', NoColor::style([])->red('x'));
    }
}
