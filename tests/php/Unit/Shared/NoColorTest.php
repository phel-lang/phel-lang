<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\NoColor;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

final class NoColorTest extends TestCase
{
    protected function tearDown(): void
    {
        NoColor::followOutput(null);
    }

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

    public function test_plain_output_decision_overrides_the_environment(): void
    {
        NoColor::followOutput(false);

        self::assertSame('x', NoColor::style()->red('x'));
    }

    public function test_decorated_output_decision_overrides_no_color(): void
    {
        $previous = getenv('NO_COLOR');
        putenv('NO_COLOR=1');
        NoColor::followOutput(true);

        try {
            self::assertFalse(NoColor::isRequested());
        } finally {
            putenv($previous === false ? 'NO_COLOR' : 'NO_COLOR=' . $previous);
        }
    }

    public function test_an_explicit_environment_ignores_the_output_decision(): void
    {
        NoColor::followOutput(false);

        self::assertFalse(NoColor::isRequested([]));
    }
}
