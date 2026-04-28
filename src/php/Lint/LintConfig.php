<?php

declare(strict_types=1);

namespace Phel\Lint;

use Gacela\Framework\AbstractConfig;
use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Config\RuleSettings;

final class LintConfig extends AbstractConfig
{
    public const string DEFAULT_CONFIG_FILENAME = 'phel-lint.phel';

    public const string CACHE_DIR = '.phel/lint-cache';

    /**
     * Default severities for every rule shipped in v1. Rules not listed
     * here are disabled by default and must be opted into via config.
     *
     * @return array<string, string>
     */
    public static function defaultSeverities(): array
    {
        return [
            RuleRegistry::UNRESOLVED_SYMBOL => Diagnostic::SEVERITY_ERROR,
            RuleRegistry::ARITY_MISMATCH => Diagnostic::SEVERITY_ERROR,
            RuleRegistry::INVALID_DESTRUCTURING => Diagnostic::SEVERITY_ERROR,
            RuleRegistry::DUPLICATE_KEY => Diagnostic::SEVERITY_ERROR,
            RuleRegistry::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING,
            RuleRegistry::UNUSED_REQUIRE => Diagnostic::SEVERITY_WARNING,
            RuleRegistry::UNUSED_IMPORT => Diagnostic::SEVERITY_WARNING,
            RuleRegistry::SHADOWED_BINDING => Diagnostic::SEVERITY_WARNING,
            RuleRegistry::REDUNDANT_DO => Diagnostic::SEVERITY_WARNING,
            RuleRegistry::DISCOURAGED_VAR => Diagnostic::SEVERITY_WARNING,
        ];
    }

    public static function defaultConfigFilename(): string
    {
        return self::DEFAULT_CONFIG_FILENAME;
    }

    public static function defaultCacheDir(): string
    {
        return self::CACHE_DIR;
    }

    public function defaultSettings(): RuleSettings
    {
        return RuleSettings::fromMap(self::defaultSeverities());
    }
}
