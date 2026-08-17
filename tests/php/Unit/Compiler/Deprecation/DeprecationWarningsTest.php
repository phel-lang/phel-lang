<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Deprecation;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

use function dirname;
use function ini_get;
use function restore_error_handler;

use function set_error_handler;

use function sprintf;

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

    public function test_source_gate_suppresses_a_dependency(): void
    {
        DeprecationWarnings::enable();

        $vendorFile = '/app/vendor/acme/lib/src/thing.phel';

        self::assertTrue(DeprecationWarnings::isThirdPartySource($vendorFile));
        self::assertFalse(DeprecationWarnings::isReportableSource($vendorFile));
        self::assertSame([], $this->capture(static function () use ($vendorFile): void {
            DeprecationWarnings::warnForSource($vendorFile, 'a dependency deprecation');
        }));
    }

    public function test_a_vendor_named_segment_is_only_matched_whole(): void
    {
        self::assertFalse(DeprecationWarnings::isThirdPartySource('/app/vendored/src/thing.phel'));
        self::assertFalse(DeprecationWarnings::isThirdPartySource('/app/my-vendor/src/thing.phel'));
        self::assertTrue(DeprecationWarnings::isThirdPartySource('/app/vendor'));
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

    /**
     * A notice found while a compile is being recorded is kept whether or not
     * the flag is on, so a cached compile can report it later (#3222); it is
     * raised now only when the flag is on.
     */
    public function test_recording_keeps_a_notice_the_flag_would_have_dropped(): void
    {
        DeprecationWarnings::disable();
        DeprecationWarnings::startRecording();

        $raised = $this->capture(static function (): void {
            DeprecationWarnings::warn('plain');
            DeprecationWarnings::warnOnceForSource('/app/user.phel', 'subject', 'once');
            DeprecationWarnings::warnOnceForSource('/app/user.phel', 'subject', 'twice');
        });

        self::assertSame([], $raised, 'the flag is off, nothing is raised');
        self::assertSame(
            [
                ['message' => 'plain', 'announced' => false],
                ['message' => 'once', 'announced' => false],
            ],
            DeprecationWarnings::stopRecording(),
        );
    }

    public function test_recording_and_raising_see_the_same_notices_when_the_flag_is_on(): void
    {
        DeprecationWarnings::enable();
        DeprecationWarnings::startRecording();

        $raised = $this->capture(static function (): void {
            DeprecationWarnings::warn('surfaced');
        });

        self::assertSame(['surfaced'], $raised);
        self::assertSame([['message' => 'surfaced', 'announced' => false]], DeprecationWarnings::stopRecording());
    }

    public function test_recording_marks_an_announced_notice_and_nests(): void
    {
        DeprecationWarnings::disable();
        $location = new SourceLocation('/app/outer.phel', 3, 1);

        DeprecationWarnings::startRecording();
        DeprecationWarnings::warn('outer');
        DeprecationWarnings::startRecording();
        $this->capture(static function () use ($location): void {
            DeprecationWarnings::announceOnceAtOrigin($location, 'sep', static fn(string $file, int $line): string => sprintf('%s:%d announced', $file, $line));
        });
        $inner = DeprecationWarnings::stopRecording();
        $outer = DeprecationWarnings::stopRecording();

        self::assertSame([['message' => '/app/outer.phel:3 announced', 'announced' => true]], $inner);
        self::assertSame([['message' => 'outer', 'announced' => false]], $outer, 'a nested compile records into its own frame');
    }

    public function test_recording_still_skips_the_stdlib_and_dependencies(): void
    {
        DeprecationWarnings::disable();
        DeprecationWarnings::startRecording();
        DeprecationWarnings::warnForSource(dirname(__DIR__, 5) . '/src/phel/walk.phel', 'stdlib');
        DeprecationWarnings::warnForSource('/app/vendor/acme/lib/src/thing.phel', 'vendor');

        self::assertSame([], DeprecationWarnings::stopRecording());
    }

    public function test_replay_raises_recorded_notices_by_their_own_rule(): void
    {
        $recorded = [
            ['message' => 'gated', 'announced' => false],
            ['message' => 'announced anyway', 'announced' => true],
        ];

        DeprecationWarnings::disable();
        self::assertSame(['announced anyway'], $this->capture(static function () use ($recorded): void {
            DeprecationWarnings::replay($recorded);
        }));

        DeprecationWarnings::enable();
        self::assertSame(['gated', 'announced anyway'], $this->capture(static function () use ($recorded): void {
            DeprecationWarnings::replay($recorded);
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

    public function test_warn_once_at_origin_reports_the_location_itself_when_nothing_expanded_it(): void
    {
        DeprecationWarnings::enable();

        self::assertSame(['at /app/user.phel:4'], $this->capture(static function (): void {
            DeprecationWarnings::warnOnceAtOrigin(
                new SourceLocation('/app/user.phel', 4, 1),
                'subject',
                static fn(string $file, int $line): string => sprintf('at %s:%d', $file, $line),
            );
        }));
    }

    public function test_warn_once_at_origin_reports_the_expansion_origin_and_names_the_call_site(): void
    {
        DeprecationWarnings::enable();

        $captured = $this->capture(static function (): void {
            DeprecationWarnings::warnOnceAtOrigin(
                new SourceLocation('/app/user.phel', 4, 1, new SourceLocation('/app/macros.phel', 9, 2)),
                'subject',
                static fn(string $file, int $line): string => sprintf('at %s:%d', $file, $line),
            );
        });

        self::assertSame(
            ['at /app/macros.phel:9 (reached by expanding a macro at /app/user.phel:4)'],
            $captured,
        );
    }

    public function test_warn_once_at_origin_dedups_against_the_origin_file(): void
    {
        DeprecationWarnings::enable();

        // Two different call sites reaching the same macro are one edit, so
        // they must not produce two notices.
        self::assertCount(1, $this->capture(static function (): void {
            foreach ([4, 11] as $line) {
                DeprecationWarnings::warnOnceAtOrigin(
                    new SourceLocation('/app/user.phel', $line, 1, new SourceLocation('/app/macros.phel', 9, 2)),
                    'subject',
                    static fn(string $file, int $callLine): string => sprintf('at %s:%d', $file, $callLine),
                );
            }
        }));
    }

    public function test_warn_once_at_origin_suppresses_a_bundled_stdlib_origin(): void
    {
        DeprecationWarnings::enable();

        $stdlibFile = dirname(__DIR__, 5) . '/src/phel/core/lazy.phel';

        self::assertSame([], $this->capture(static function () use ($stdlibFile): void {
            DeprecationWarnings::warnOnceAtOrigin(
                new SourceLocation('/app/user.phel', 4, 1, new SourceLocation($stdlibFile, 9, 2)),
                'subject',
                static fn(string $file, int $line): string => sprintf('at %s:%d', $file, $line),
            );
        }));
    }

    public function test_warn_once_at_origin_stays_silent_for_an_unknown_origin(): void
    {
        DeprecationWarnings::enable();

        self::assertSame([], $this->capture(static function (): void {
            DeprecationWarnings::warnOnceAtOrigin(
                new SourceLocation('/app/user.phel', 4, 1, SourceLocation::unknown()),
                'subject',
                static fn(string $file, int $line): string => sprintf('at %s:%d', $file, $line),
            );
        }));
    }

    public function test_warn_syntax_reports_an_expansion_against_the_macro_file(): void
    {
        DeprecationWarnings::enable();

        $captured = $this->capture(static function (): void {
            DeprecationWarnings::warnSyntax(
                '"|()"',
                'short fn literals',
                '"#()"',
                new SourceLocation('/app/user.phel', 4, 1, new SourceLocation('/app/macros.phel', 9, 2)),
            );
        });

        self::assertCount(1, $captured);
        self::assertStringContainsString('(at /app/macros.phel:9:2)', $captured[0]);
        self::assertStringContainsString('reached by expanding a macro at /app/user.phel:4', $captured[0]);
    }

    public function test_warn_syntax_suppresses_an_expansion_of_a_bundled_stdlib_macro(): void
    {
        DeprecationWarnings::enable();

        $stdlibFile = dirname(__DIR__, 5) . '/src/phel/core/lazy.phel';

        self::assertSame([], $this->capture(static function () use ($stdlibFile): void {
            DeprecationWarnings::warnSyntax(
                '"|()"',
                'short fn literals',
                '"#()"',
                new SourceLocation('/app/user.phel', 4, 1, new SourceLocation($stdlibFile, 9, 2)),
            );
        }));
    }

    /**
     * A notice raised while an output buffer is open must not land in that
     * buffer. The emitter builds generated PHP inside `ob_start()`, so under
     * PHP CLI's default `display_errors=1` (STDOUT) a notice raised there used
     * to be spliced into the emitted code and break the compile (#2827). No
     * detector runs during emission today; this pins the guard so the next one
     * cannot reintroduce it.
     */
    public function test_notice_display_never_reaches_a_captured_stdout_buffer(): void
    {
        DeprecationWarnings::enable();

        self::assertSame('', $this->captureStdoutWithPhpDefaultHandler(static function (): void {
            DeprecationWarnings::warn('buffered deprecation');
        }));
    }

    public function test_notice_display_stays_silent_when_the_user_turned_display_errors_off(): void
    {
        DeprecationWarnings::enable();

        // Redirecting to stderr must not *enable* a display the user disabled,
        // so nothing reaches either channel here.
        self::assertSame('', $this->captureStdoutWithPhpDefaultHandler(
            static function (): void {
                DeprecationWarnings::warn('silenced deprecation');
            },
            displayErrors: '0',
        ));
    }

    public function test_the_stderr_redirect_is_scoped_to_the_notice(): void
    {
        DeprecationWarnings::enable();

        $previous = (string) ini_get('display_errors');
        ini_set('display_errors', 'STDOUT');

        $during = null;
        try {
            // A userland handler observes the ini while the notice is being
            // raised and suppresses the display, so this test adds no output.
            set_error_handler(static function () use (&$during): bool {
                $during = ini_get('display_errors');

                return true;
            }, E_USER_DEPRECATED);

            try {
                DeprecationWarnings::warn('scoped redirect');
            } finally {
                restore_error_handler();
            }

            self::assertSame('stderr', $during);
            self::assertSame('STDOUT', ini_get('display_errors'));
        } finally {
            ini_set('display_errors', $previous);
        }
    }

    /**
     * Runs `$fn` the way a real CLI invocation would: PHPUnit's error handler
     * set aside so PHP's own display runs, `display_errors` pointed at stdout,
     * and an output buffer open the way the emitter keeps one. Returns whatever
     * reached that buffer.
     *
     * `error_reporting` has to be widened too — PHPUnit masks
     * `E_USER_DEPRECATED` out, and with it masked PHP prints nothing at all, so
     * the assertion would hold for the wrong reason. `log_errors` is off so the
     * run does not also write the notice to the suite's stderr.
     */
    private function captureStdoutWithPhpDefaultHandler(callable $fn, string $displayErrors = 'STDOUT'): string
    {
        $previousDisplay = (string) ini_get('display_errors');
        $previousLog = (string) ini_get('log_errors');
        $previousReporting = error_reporting(E_ALL);
        ini_set('display_errors', $displayErrors);
        ini_set('log_errors', '0');
        set_error_handler(null);
        ob_start();

        try {
            $fn();
        } finally {
            $captured = (string) ob_get_clean();
            restore_error_handler();
            ini_set('log_errors', $previousLog);
            ini_set('display_errors', $previousDisplay);
            error_reporting($previousReporting);
        }

        return $captured;
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
