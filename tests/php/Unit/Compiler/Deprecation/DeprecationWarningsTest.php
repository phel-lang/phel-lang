<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Deprecation;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
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
