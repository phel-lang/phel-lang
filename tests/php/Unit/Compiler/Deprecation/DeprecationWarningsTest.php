<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Deprecation;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

use function dirname;
use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

final class DeprecationWarningsTest extends TestCase
{
    protected function tearDown(): void
    {
        DeprecationWarnings::reset();
    }

    public function test_warn_is_silent_when_disabled(): void
    {
        DeprecationWarnings::disable();

        self::assertSame([], $this->capture(static function (): void {
            DeprecationWarnings::warn('should not surface');
        }));
    }

    public function test_warn_emits_when_enabled(): void
    {
        DeprecationWarnings::enable();

        self::assertSame(['surfaced'], $this->capture(static function (): void {
            DeprecationWarnings::warn('surfaced');
        }));
    }

    public function test_reset_restores_the_environment_driven_default(): void
    {
        DeprecationWarnings::enable();
        DeprecationWarnings::reset();

        // The suite runs without PHEL_WARN_DEPRECATIONS set, so the flag falls
        // back to off rather than staying on from the previous call.
        self::assertFalse(DeprecationWarnings::isEnabled());
    }

    public function test_source_gate_suppresses_bundled_stdlib(): void
    {
        DeprecationWarnings::enable();

        $stdlibFile = dirname(__DIR__, 5) . '/src/phel/walk.phel';

        self::assertTrue(DeprecationWarnings::isBundledStdlibSource($stdlibFile));
        self::assertFalse(DeprecationWarnings::isEnabledForSource($stdlibFile));
        self::assertSame([], $this->capture(static function () use ($stdlibFile): void {
            DeprecationWarnings::warnForSource($stdlibFile, 'stdlib deprecation');
        }));
    }

    public function test_source_gate_allows_user_sources_including_nested_src_phel(): void
    {
        DeprecationWarnings::enable();

        self::assertTrue(DeprecationWarnings::isEnabledForSource('/app/src/phel/main.phel'));
        self::assertSame(['user deprecation'], $this->capture(static function (): void {
            DeprecationWarnings::warnForSource('/app/src/phel/main.phel', 'user deprecation');
        }));
    }

    public function test_source_gate_suppresses_unknown_source(): void
    {
        DeprecationWarnings::enable();

        self::assertFalse(DeprecationWarnings::isEnabledForSource(''));
    }

    public function test_warn_once_reports_a_subject_only_once_per_file(): void
    {
        DeprecationWarnings::enable();

        self::assertSame(['first'], $this->capture(static function (): void {
            DeprecationWarnings::warnOnceForSource('/app/user.phel', 'phel.core/set-meta!', 'first');
            DeprecationWarnings::warnOnceForSource('/app/user.phel', 'phel.core/set-meta!', 'second');
        }));
    }

    public function test_warn_once_reports_the_same_subject_again_in_another_file(): void
    {
        DeprecationWarnings::enable();

        self::assertCount(2, $this->capture(static function (): void {
            DeprecationWarnings::warnOnceForSource('/app/a.phel', 'phel.core/set-meta!', 'in a');
            DeprecationWarnings::warnOnceForSource('/app/b.phel', 'phel.core/set-meta!', 'in b');
        }));
    }

    public function test_warn_once_obeys_the_switch_and_the_stdlib_suppression(): void
    {
        DeprecationWarnings::disable();
        self::assertSame([], $this->capture(static function (): void {
            DeprecationWarnings::warnOnceForSource('/app/user.phel', 'subject', 'off');
        }));

        DeprecationWarnings::enable();
        $stdlibFile = dirname(__DIR__, 5) . '/src/phel/walk.phel';
        self::assertSame([], $this->capture(static function () use ($stdlibFile): void {
            DeprecationWarnings::warnOnceForSource($stdlibFile, 'subject', 'stdlib');
        }));
    }

    public function test_reset_clears_the_dedup_table(): void
    {
        DeprecationWarnings::enable();
        $this->capture(static function (): void {
            DeprecationWarnings::warnOnceForSource('/app/user.phel', 'subject', 'first');
        });

        DeprecationWarnings::reset();
        DeprecationWarnings::enable();

        self::assertSame(['again'], $this->capture(static function (): void {
            DeprecationWarnings::warnOnceForSource('/app/user.phel', 'subject', 'again');
        }));
    }

    public function test_syntax_message_has_one_shape_and_no_room_for_a_version(): void
    {
        $message = DeprecationWarnings::syntaxMessage(
            '"|()"',
            'short functions',
            '"#()"',
            new SourceLocation('/app/user.phel', 7, 3),
        );

        self::assertSame(
            'Using "|()" for short functions is deprecated and will be removed in a future release; '
            . 'use "#()" instead (at /app/user.phel:7:3)',
            $message,
        );
        // The factory takes no version argument, so a notice cannot promise a
        // release that later ships and goes stale (#2783).
        self::assertDoesNotMatchRegularExpression('/v?\d+\.\d+(\.\d+)?/', $message);
    }

    public function test_syntax_message_omits_the_location_when_there_is_none(): void
    {
        self::assertSame(
            'Using "," for unquote is deprecated and will be removed in a future release; use "~" instead',
            DeprecationWarnings::syntaxMessage('","', 'unquote', '"~"', null),
        );
    }

    public function test_warn_syntax_is_gated_on_the_same_switch(): void
    {
        $location = new SourceLocation('/app/user.phel', 1, 1);

        DeprecationWarnings::disable();
        self::assertSame([], $this->capture(static function () use ($location): void {
            DeprecationWarnings::warnSyntax('"x"', 'things', '"y"', $location);
        }));

        DeprecationWarnings::enable();
        self::assertCount(1, $this->capture(static function () use ($location): void {
            DeprecationWarnings::warnSyntax('"x"', 'things', '"y"', $location);
        }));
    }

    /**
     * @return list<string>
     */
    private function capture(callable $fn): array
    {
        $messages = [];
        set_error_handler(
            static function (int $errno, string $message) use (&$messages): bool {
                $messages[] = $message;

                return true;
            },
            E_USER_DEPRECATED,
        );

        try {
            $fn();
        } finally {
            restore_error_handler();
        }

        return $messages;
    }
}
