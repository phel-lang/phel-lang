<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Application;

use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Console\Application\WarnDeprecationsFlag;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesDeprecationsTrait;
use PHPUnit\Framework\TestCase;

final class WarnDeprecationsFlagTest extends TestCase
{
    use CapturesDeprecationsTrait;

    protected function tearDown(): void
    {
        // The switch is process-wide: leaving it on would make unrelated tests
        // in this run start emitting deprecations.
        $this->stopCapturingDeprecations();
    }

    public function test_strips_plain_flag_and_enables_deprecation_warnings(): void
    {
        DeprecationWarnings::disable();

        WarnDeprecationsFlag::applyAndStrip(['phel', 'run', '--warn-deprecations', 'src/main.phel']);

        self::assertTrue(DeprecationWarnings::isEnabled());
    }

    public function test_leaves_deprecation_warnings_untouched_when_flag_absent(): void
    {
        DeprecationWarnings::disable();

        WarnDeprecationsFlag::applyAndStrip(['phel', 'run', 'src/main.phel']);

        self::assertFalse(DeprecationWarnings::isEnabled());
    }

    public function test_returns_argv_unchanged_when_flag_absent(): void
    {
        $argv = ['phel', 'test', '--filter=foo'];

        self::assertSame($argv, WarnDeprecationsFlag::applyAndStrip($argv));
    }

    /**
     * The flag flips one switch, and every detector reads that switch, so
     * turning it on is enough to make the analyzer's backslash detector fire
     * without the flag knowing the detector exists.
     */
    public function test_the_one_switch_reaches_the_analyzer_detectors(): void
    {
        DeprecationWarnings::disable();
        $this->captureDeprecationsWithoutEnabling();

        $result = WarnDeprecationsFlag::applyAndStrip(
            ['phel', 'test', '--warn-deprecations', '--filter=foo'],
        );

        self::assertSame(['phel', 'test', '--filter=foo'], $result);

        BackslashSeparatorDeprecator::getInstance()->maybeWarn(
            $this->locatedRawNameSymbol('phel\\core/map', '/app/user.phel'),
        );

        self::assertCount(1, $this->capturedDeprecations());
    }

    public function test_strips_flag_with_value_form(): void
    {
        $result = WarnDeprecationsFlag::applyAndStrip(
            ['phel', 'run', '--warn-deprecations=1', 'src/main.phel'],
        );

        self::assertSame(['phel', 'run', 'src/main.phel'], $result);
    }

    public function test_detectors_stay_silent_when_the_flag_is_absent(): void
    {
        DeprecationWarnings::disable();
        $this->captureDeprecationsWithoutEnabling();

        $result = WarnDeprecationsFlag::applyAndStrip(['phel', 'test']);

        self::assertSame(['phel', 'test'], $result);

        BackslashSeparatorDeprecator::getInstance()->maybeWarn(
            $this->locatedRawNameSymbol('phel\\core/map', '/app/user.phel'),
        );

        self::assertSame([], $this->capturedDeprecations());
    }

    /**
     * Installs the capture handler but leaves the switch exactly as the test
     * set it, so `applyAndStrip` is the only thing that can turn it on.
     */
    private function captureDeprecationsWithoutEnabling(): void
    {
        $enabled = DeprecationWarnings::isEnabled();
        $this->startCapturingDeprecations();
        $enabled ? DeprecationWarnings::enable() : DeprecationWarnings::disable();
    }

    private function locatedRawNameSymbol(string $rawName, string $file): Symbol
    {
        $symbol = Symbol::createForNamespace(null, $rawName);
        $symbol->setStartLocation(new SourceLocation($file, 1, 1));

        return $symbol;
    }
}
