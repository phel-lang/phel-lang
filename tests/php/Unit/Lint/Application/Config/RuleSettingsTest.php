<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Config;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Config\RuleSettings;
use PHPUnit\Framework\TestCase;

final class RuleSettingsTest extends TestCase
{
    public function test_it_builds_from_a_severity_map_and_drops_invalid_entries(): void
    {
        $settings = RuleSettings::fromMap([
            RuleRegistry::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING,
            RuleRegistry::ARITY_MISMATCH => 'bogus-severity',
        ]);

        self::assertTrue($settings->isEnabled(RuleRegistry::UNUSED_BINDING));
        self::assertFalse($settings->isEnabled(RuleRegistry::ARITY_MISMATCH));
    }

    public function test_it_merges_overrides_and_off_disables_a_rule(): void
    {
        $base = RuleSettings::fromMap([
            RuleRegistry::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING,
        ]);

        $merged = $base->withOverrides(
            [RuleRegistry::UNUSED_BINDING => RuleSettings::SEVERITY_OFF],
            [],
        );

        self::assertFalse($merged->isEnabled(RuleRegistry::UNUSED_BINDING));
    }

    public function test_it_matches_path_glob_exclusion(): void
    {
        $settings = new RuleSettings(
            severities: [RuleRegistry::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING],
            excludeGlobs: [RuleRegistry::UNUSED_BINDING => ['src/phel/*.phel']],
        );

        self::assertTrue($settings->isExcluded(
            RuleRegistry::UNUSED_BINDING,
            'src/phel/local.phel',
            'phel\\local',
        ));
    }

    public function test_it_matches_namespace_glob_exclusion(): void
    {
        $settings = new RuleSettings(
            severities: [RuleRegistry::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING],
            excludeGlobs: [RuleRegistry::UNUSED_BINDING => ['phel\\experimental*']],
        );

        self::assertTrue($settings->isExcluded(
            RuleRegistry::UNUSED_BINDING,
            '/tmp/foo.phel',
            'phel\\experimental\\sub',
        ));
    }

    public function test_it_falls_back_to_off_for_unknown_rule_code(): void
    {
        $settings = RuleSettings::fromMap([]);

        self::assertSame(RuleSettings::SEVERITY_OFF, $settings->severityFor('phel/nope'));
        self::assertFalse($settings->isEnabled('phel/nope'));
    }
}
