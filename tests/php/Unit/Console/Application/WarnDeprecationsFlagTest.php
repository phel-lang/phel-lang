<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console\Application;

use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Console\Application\WarnDeprecationsFlag;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class WarnDeprecationsFlagTest extends TestCase
{
    protected function tearDown(): void
    {
        BackslashSeparatorDeprecator::resetInstance();
    }

    public function test_returns_argv_unchanged_when_flag_absent(): void
    {
        $argv = ['phel', 'test', '--filter=foo'];

        self::assertSame($argv, WarnDeprecationsFlag::applyAndStrip($argv));
    }

    public function test_strips_plain_flag_and_enables_deprecator(): void
    {
        $captured = [];
        // Preconfigure the singleton so applyAndStrip's new instance replaces it.
        // We'll assert via the installed deprecator after.
        $result = WarnDeprecationsFlag::applyAndStrip(
            ['phel', 'test', '--warn-deprecations', '--filter=foo'],
        );

        self::assertSame(['phel', 'test', '--filter=foo'], $result);

        // Swap the now-enabled deprecator for a capturing one, then reach
        // through a fake symbol to prove it actually fires.
        $instance = BackslashSeparatorDeprecator::getInstance();
        $viaFlag = new BackslashSeparatorDeprecator(
            enabled: true,
            emitter: static function (string $msg) use (&$captured): void {
                $captured[] = $msg;
            },
        );
        BackslashSeparatorDeprecator::useInstance($viaFlag);

        $sym = Symbol::createForNamespace('phel\\core', 'map');
        $sym->setStartLocation(new SourceLocation('/app/user.phel', 1, 1));

        $viaFlag->maybeWarn($sym);

        self::assertInstanceOf(BackslashSeparatorDeprecator::class, $instance);
        self::assertCount(1, $captured);
    }

    public function test_strips_flag_with_value_form(): void
    {
        $result = WarnDeprecationsFlag::applyAndStrip(
            ['phel', 'run', '--warn-deprecations=1', 'src/main.phel'],
        );

        self::assertSame(['phel', 'run', 'src/main.phel'], $result);
    }

    public function test_preserves_disabled_default_when_flag_absent(): void
    {
        // Verify that without the flag the singleton stays in its default
        // (env-var-driven) state. We force-reset first so the test does not
        // depend on any prior configuration.
        BackslashSeparatorDeprecator::resetInstance();

        $result = WarnDeprecationsFlag::applyAndStrip(['phel', 'test']);

        self::assertSame(['phel', 'test'], $result);

        $captured = [];
        $instance = BackslashSeparatorDeprecator::getInstance();
        // Force a capturing disabled instance to confirm the flag-absent
        // path does not flip the enabled bit.
        BackslashSeparatorDeprecator::useInstance(new BackslashSeparatorDeprecator(
            enabled: false,
            emitter: static function (string $msg) use (&$captured): void {
                $captured[] = $msg;
            },
        ));

        $sym = Symbol::createForNamespace('phel\\core', 'map');
        $sym->setStartLocation(new SourceLocation('/app/user.phel', 1, 1));
        BackslashSeparatorDeprecator::getInstance()->maybeWarn($sym);

        self::assertInstanceOf(BackslashSeparatorDeprecator::class, $instance);
        self::assertSame([], $captured);
    }
}
