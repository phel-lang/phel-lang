<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Cache;

use Phel\Lint\Application\Cache\LintCacheFingerprint;
use PHPUnit\Framework\TestCase;

final class LintCacheFingerprintTest extends TestCase
{
    public function test_a_new_phel_release_invalidates_cached_results(): void
    {
        self::assertNotSame(
            LintCacheFingerprint::of('v1.0.0-rc1', ['phel/a'], 'settings'),
            LintCacheFingerprint::of('v1.0.0-rc2', ['phel/a'], 'settings'),
        );
    }

    public function test_rule_order_does_not_matter(): void
    {
        self::assertSame(
            LintCacheFingerprint::of('v1', ['phel/a', 'phel/b'], 'settings'),
            LintCacheFingerprint::of('v1', ['phel/b', 'phel/a'], 'settings'),
        );
    }

    public function test_a_rule_or_settings_change_invalidates_cached_results(): void
    {
        $base = LintCacheFingerprint::of('v1', ['phel/a'], 'settings');

        self::assertNotSame($base, LintCacheFingerprint::of('v1', ['phel/a', 'phel/b'], 'settings'));
        self::assertNotSame($base, LintCacheFingerprint::of('v1', ['phel/a'], 'other'));
    }
}
