<?php

declare(strict_types=1);

namespace Phel\Lint;

use Gacela\Framework\AbstractConfig;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\LintRuleCodes;

/**
 * @internal
 */
final class LintConfig extends AbstractConfig
{
    public const string CACHE_SUBPATH = 'lint-cache';

    private const string DEFAULT_CONFIG_FILENAME = 'phel-lint.phel';

    /**
     * Default severities for every rule shipped in v1. Rules not listed
     * here are disabled by default and must be opted into via config.
     *
     * @return array<string, string>
     */
    public static function defaultSeverities(): array
    {
        return [
            LintRuleCodes::UNRESOLVED_SYMBOL => Diagnostic::SEVERITY_ERROR,
            LintRuleCodes::ARITY_MISMATCH => Diagnostic::SEVERITY_ERROR,
            LintRuleCodes::INVALID_DESTRUCTURING => Diagnostic::SEVERITY_ERROR,
            LintRuleCodes::DUPLICATE_KEY => Diagnostic::SEVERITY_ERROR,
            LintRuleCodes::DUPLICATE_DEF => Diagnostic::SEVERITY_ERROR,
            LintRuleCodes::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING,
            LintRuleCodes::UNUSED_REQUIRE => Diagnostic::SEVERITY_WARNING,
            LintRuleCodes::UNUSED_IMPORT => Diagnostic::SEVERITY_WARNING,
            LintRuleCodes::SHADOWED_BINDING => Diagnostic::SEVERITY_WARNING,
            LintRuleCodes::REDUNDANT_DO => Diagnostic::SEVERITY_WARNING,
            LintRuleCodes::DISCOURAGED_VAR => Diagnostic::SEVERITY_WARNING,
            LintRuleCodes::COMMENT_STYLE => Diagnostic::SEVERITY_WARNING,
        ];
    }

    public static function defaultConfigFilename(): string
    {
        return self::DEFAULT_CONFIG_FILENAME;
    }

    public function defaultSettings(): RuleSettings
    {
        return RuleSettings::fromMap(self::defaultSeverities());
    }
}
