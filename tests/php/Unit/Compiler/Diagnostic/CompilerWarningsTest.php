<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Diagnostic;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Compiler\Domain\Diagnostic\CompilerWarnings;
use PhelTest\Support\CapturesCompilerWarningsTrait;
use PHPUnit\Framework\TestCase;

use function dirname;

final class CompilerWarningsTest extends TestCase
{
    use CapturesCompilerWarningsTrait;

    protected function setUp(): void
    {
        $this->startCapturingCompilerWarnings();
    }

    protected function tearDown(): void
    {
        $this->stopCapturingCompilerWarnings();
        DeprecationWarnings::reset();
    }

    public function test_warns_without_any_flag_being_enabled(): void
    {
        // The load-bearing difference from DeprecationWarnings: a collision has
        // already changed which definition runs, so it cannot be opt-in.
        DeprecationWarnings::disable();

        CompilerWarnings::warnOnceForSource('/app/user.phel', 'user/doc', 'a collision');

        self::assertSame(['a collision'], $this->capturedCompilerWarnings());
    }

    public function test_dedups_per_file_and_subject(): void
    {
        CompilerWarnings::warnOnceForSource('/app/user.phel', 'user/doc', 'first');
        CompilerWarnings::warnOnceForSource('/app/user.phel', 'user/doc', 'second');
        CompilerWarnings::warnOnceForSource('/app/user.phel', 'user/other', 'third');
        CompilerWarnings::warnOnceForSource('/app/main.phel', 'user/doc', 'fourth');

        self::assertSame(['first', 'third', 'fourth'], $this->capturedCompilerWarnings());
    }

    public function test_is_silent_for_an_unknown_source(): void
    {
        CompilerWarnings::warnOnceForSource('', 'user/doc', 'a collision');

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_is_silent_for_bundled_stdlib(): void
    {
        CompilerWarnings::warnOnceForSource(
            dirname(__DIR__, 5) . '/src/phel/core/collections.phel',
            'phel.core/get',
            'a collision',
        );

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_reset_lets_a_subject_warn_again(): void
    {
        CompilerWarnings::warnOnceForSource('/app/user.phel', 'user/doc', 'first');
        CompilerWarnings::reset();
        CompilerWarnings::warnOnceForSource('/app/user.phel', 'user/doc', 'second');

        self::assertSame(['first', 'second'], $this->capturedCompilerWarnings());
    }
}
