<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Diagnostic;

use Phel\Compiler\Domain\Diagnostic\ErrorNotice;
use PHPUnit\Framework\TestCase;

use function error_reporting;
use function fopen;
use function ini_get;
use function ini_set;
use function restore_error_handler;
use function rewind;
use function set_error_handler;
use function stream_get_contents;

use const E_ALL;
use const E_USER_DEPRECATED;
use const E_USER_WARNING;

final class ErrorNoticeTest extends TestCase
{
    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $this->stream = fopen('php://memory', 'w+');
        ErrorNotice::writeTo($this->stream);
    }

    protected function tearDown(): void
    {
        ErrorNotice::reset();
    }

    public function test_writes_one_line_for_a_deprecation(): void
    {
        $this->withPhpDefaultHandler(static function (): void {
            ErrorNotice::raise('a deprecation', E_USER_DEPRECATED);
        });

        self::assertSame("deprecated: a deprecation\n", $this->written());
    }

    public function test_writes_one_line_for_a_warning(): void
    {
        $this->withPhpDefaultHandler(static function (): void {
            ErrorNotice::raise('a collision', E_USER_WARNING);
        });

        self::assertSame("warning: a collision\n", $this->written());
    }

    public function test_never_names_the_phel_file_that_raised_it(): void
    {
        // PHP's own renderer appends `in <file> on line <n>`, which pointed
        // every notice at this `@internal` class instead of the user's code.
        $this->withPhpDefaultHandler(static function (): void {
            ErrorNotice::raise('a deprecation', E_USER_DEPRECATED);
        });

        self::assertStringNotContainsString('ErrorNotice.php', $this->written());
    }

    public function test_hands_the_notice_to_a_userland_error_handler_instead_of_writing_it(): void
    {
        $handled = [];
        set_error_handler(static function (int $errno, string $message) use (&$handled): bool {
            $handled[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            ErrorNotice::raise('a deprecation', E_USER_DEPRECATED);
        } finally {
            restore_error_handler();
        }

        self::assertSame(['a deprecation'], $handled);
        self::assertSame('', $this->written());
    }

    public function test_stays_silent_when_error_reporting_masks_the_level(): void
    {
        $this->withPhpDefaultHandler(
            static function (): void {
                ErrorNotice::raise('a deprecation', E_USER_DEPRECATED);
            },
            errorReporting: E_ALL & ~E_USER_DEPRECATED,
        );

        self::assertSame('', $this->written());
    }

    public function test_stays_silent_when_the_user_turned_both_output_channels_off(): void
    {
        $previousDisplay = (string) ini_get('display_errors');
        $previousLog = (string) ini_get('log_errors');
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

        try {
            $this->withPhpDefaultHandler(static function (): void {
                ErrorNotice::raise('a deprecation', E_USER_DEPRECATED);
            });
        } finally {
            ini_set('log_errors', $previousLog);
            ini_set('display_errors', $previousDisplay);
        }

        self::assertSame('', $this->written());
    }

    /**
     * Runs `$fn` the way a real CLI invocation does: PHPUnit's error handler
     * set aside, and `error_reporting` widened because PHPUnit masks
     * `E_USER_DEPRECATED` out.
     */
    private function withPhpDefaultHandler(callable $fn, int $errorReporting = E_ALL): void
    {
        $previousReporting = error_reporting($errorReporting);
        set_error_handler(null);

        try {
            $fn();
        } finally {
            restore_error_handler();
            error_reporting($previousReporting);
        }
    }

    private function written(): string
    {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }
}
