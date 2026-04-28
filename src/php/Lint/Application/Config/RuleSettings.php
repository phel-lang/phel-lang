<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Config;

use Phel\Api\Transfer\Diagnostic;

use function array_key_exists;
use function fnmatch;
use function in_array;

/**
 * Per-rule severities plus opt-out patterns. Values are the constants
 * defined on `Diagnostic` (`error`, `warning`, `info`, `hint`) plus
 * `off` which disables the rule entirely.
 */
final readonly class RuleSettings
{
    public const string SEVERITY_OFF = 'off';

    private const array VALID_SEVERITIES = [
        Diagnostic::SEVERITY_ERROR,
        Diagnostic::SEVERITY_WARNING,
        Diagnostic::SEVERITY_INFO,
        Diagnostic::SEVERITY_HINT,
        self::SEVERITY_OFF,
    ];

    /**
     * @param array<string, string>       $severities   ruleCode => severity
     * @param array<string, list<string>> $excludeGlobs ruleCode => list of glob patterns (file path OR namespace)
     */
    public function __construct(
        public array $severities,
        public array $excludeGlobs = [],
    ) {}

    /**
     * @param array<string, string> $map
     */
    public static function fromMap(array $map): self
    {
        $severities = [];
        foreach ($map as $code => $severity) {
            if (in_array($severity, self::VALID_SEVERITIES, true)) {
                $severities[$code] = $severity;
            }
        }

        return new self($severities, []);
    }

    /**
     * Merge another set of settings on top of this one (other wins).
     *
     * @param array<string, string>       $severities
     * @param array<string, list<string>> $excludeGlobs
     */
    public function withOverrides(array $severities, array $excludeGlobs): self
    {
        $merged = $this->severities;
        foreach ($severities as $code => $sev) {
            if (in_array($sev, self::VALID_SEVERITIES, true)) {
                $merged[$code] = $sev;
            }
        }

        $mergedGlobs = $this->excludeGlobs;
        foreach ($excludeGlobs as $code => $patterns) {
            $existing = $mergedGlobs[$code] ?? [];
            foreach ($patterns as $pattern) {
                if (!in_array($pattern, $existing, true)) {
                    $existing[] = $pattern;
                }
            }

            $mergedGlobs[$code] = $existing;
        }

        return new self($merged, $mergedGlobs);
    }

    public function severityFor(string $ruleCode): string
    {
        return $this->severities[$ruleCode] ?? self::SEVERITY_OFF;
    }

    public function isEnabled(string $ruleCode): bool
    {
        return array_key_exists($ruleCode, $this->severities)
            && $this->severities[$ruleCode] !== self::SEVERITY_OFF;
    }

    /**
     * Should diagnostics for this rule be suppressed for the given file or namespace?
     */
    public function isExcluded(string $ruleCode, string $filePath, string $namespace): bool
    {
        $patterns = $this->excludeGlobs[$ruleCode] ?? [];
        if ($patterns === []) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($pattern, $filePath, $namespace)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $pattern, string $filePath, string $namespace): bool
    {
        if ($pattern === '') {
            return false;
        }

        // A pattern may match either the file path or the namespace; we try
        // both so a user doesn't have to know which representation we compare.
        // `FNM_NOESCAPE` keeps `\\` literal so Phel namespaces like
        // `phel\\core` match without the caller needing to escape them.
        if (fnmatch($pattern, $filePath, FNM_NOESCAPE)) {
            return true;
        }

        return $namespace !== '' && fnmatch($pattern, $namespace, FNM_NOESCAPE);
    }
}
