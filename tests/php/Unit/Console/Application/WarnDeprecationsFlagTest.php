<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Application;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Console\Application\WarnDeprecationsFlag;
use PhelTest\Support\CapturesDeprecationsTrait;
use PHPUnit\Framework\TestCase;

/**
 * The flag only strips. Turning the switch on moved to the compiler facade,
 * which owns it, and `ConsoleBootstrap` is what calls it after comparing the
 * two argv lists (#3048). What is pinned here is the stripping and, just as
 * importantly, that stripping alone changes no global state.
 */
final class WarnDeprecationsFlagTest extends TestCase
{
    use CapturesDeprecationsTrait;

    protected function tearDown(): void
    {
        // The switch is process-wide: leaving it on would make unrelated tests
        // in this run start emitting deprecations.
        $this->stopCapturingDeprecations();
    }

    public function test_strips_the_plain_flag(): void
    {
        self::assertSame(
            ['phel', 'run', 'src/main.phel'],
            WarnDeprecationsFlag::strip(['phel', 'run', '--warn-deprecations', 'src/main.phel']),
        );
    }

    public function test_strips_the_value_form(): void
    {
        self::assertSame(
            ['phel', 'run', 'src/main.phel'],
            WarnDeprecationsFlag::strip(['phel', 'run', '--warn-deprecations=1', 'src/main.phel']),
        );
    }

    public function test_returns_argv_unchanged_when_the_flag_is_absent(): void
    {
        $argv = ['phel', 'test', '--filter=foo'];

        self::assertSame($argv, WarnDeprecationsFlag::strip($argv));
    }

    public function test_returns_a_list_after_stripping_a_middle_argument(): void
    {
        // `array_filter` preserves keys, so the result is re-indexed: an
        // `ArgvInput` built from a sparse array silently loses arguments.
        self::assertSame(
            [0, 1, 2],
            array_keys(WarnDeprecationsFlag::strip(['phel', '--warn-deprecations', 'run', 'a.phel'])),
        );
    }

    public function test_stripping_does_not_touch_the_deprecation_switch(): void
    {
        // The separation this class exists for: whether the flag was present
        // is the caller's business, and only the compiler flips the switch.
        DeprecationWarnings::disable();

        WarnDeprecationsFlag::strip(['phel', 'run', '--warn-deprecations', 'src/main.phel']);

        self::assertFalse(DeprecationWarnings::isEnabled());
    }
}
