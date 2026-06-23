<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Config;

use Phel\Config\PhelConfig;
use Phel\Shared\ScalarCoercion;

use function array_key_exists;
use function array_map;
use function in_array;
use function is_array;
use function is_dir;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function ucfirst;

/**
 * Inspects the effective, merged Phel configuration and reports problems with
 * it. This is where the otherwise-unused {@see PhelConfig::validate()} rules
 * are actually surfaced to the user, alongside checks (such as missing
 * directories) that need the project root and so cannot live in the pure
 * Config leaf module.
 *
 * Diagnostics are advisory: the CLI commands decide whether to fail.
 */
final class ConfigDiagnostics
{
    /** @var list<int> */
    private const array SUPPORTED_OPTIMIZATION_LEVELS = [0, 1, 2];

    /**
     * @param array<string, mixed> $values      the effective merged config,
     *                                          shaped like {@see PhelConfig::jsonSerialize()}
     * @param string               $projectRoot directory that relative config
     *                                          paths resolve against
     *
     * @return list<ConfigIssue>
     */
    public function analyze(array $values, string $projectRoot): array
    {
        return [
            ...$this->relativePathErrors($values),
            ...$this->typeMismatchWarnings($values),
            ...$this->emptySourceWarnings($values),
            ...$this->missingDirectoryWarnings($values, $projectRoot),
            ...$this->optimizationLevelWarnings($values),
        ];
    }

    /**
     * Runs the pure {@see PhelConfig::validate()} rules against the effective
     * config (e.g. directories that must be relative).
     *
     * @param array<string, mixed> $values
     *
     * @return list<ConfigIssue>
     */
    private function relativePathErrors(array $values): array
    {
        $config = new PhelConfig()
            ->withSrcDirs(ScalarCoercion::toStringList($values[PhelConfig::SRC_DIRS] ?? null))
            ->withTestDirs(ScalarCoercion::toStringList($values[PhelConfig::TEST_DIRS] ?? null))
            ->withVendorDir(ScalarCoercion::toString($values[PhelConfig::VENDOR_DIR] ?? null));

        return array_map(
            ConfigIssue::error(...),
            $config->validate(),
        );
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<ConfigIssue>
     */
    private function emptySourceWarnings(array $values): array
    {
        if (!array_key_exists(PhelConfig::SRC_DIRS, $values)) {
            return [];
        }

        // A non-array value is a type mismatch, reported separately; do not
        // also claim "no source directories" for it.
        $raw = $values[PhelConfig::SRC_DIRS];
        if (!is_array($raw) || $raw !== []) {
            return [];
        }

        return [ConfigIssue::warning('No source directories are configured; nothing will be compiled')];
    }

    /**
     * Relative source/test directories that do not exist on disk are almost
     * always a typo. Absolute paths are intentionally skipped here: they are
     * already flagged by the relative-path rule, so we avoid double-reporting.
     *
     * @param array<string, mixed> $values
     *
     * @return list<ConfigIssue>
     */
    private function missingDirectoryWarnings(array $values, string $projectRoot): array
    {
        $root = rtrim($projectRoot, '/');
        $groups = [
            'source' => ScalarCoercion::toStringList($values[PhelConfig::SRC_DIRS] ?? null),
            'test' => ScalarCoercion::toStringList($values[PhelConfig::TEST_DIRS] ?? null),
        ];

        $issues = [];
        foreach ($groups as $label => $dirs) {
            foreach ($dirs as $dir) {
                if ($dir === '') {
                    continue;
                }

                if (str_starts_with($dir, '/')) {
                    continue;
                }

                if (!is_dir($root . '/' . $dir)) {
                    $issues[] = ConfigIssue::warning(sprintf(
                        "%s directory '%s' does not exist",
                        ucfirst($label),
                        $dir,
                    ));
                }
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<ConfigIssue>
     */
    private function optimizationLevelWarnings(array $values): array
    {
        if (!array_key_exists(PhelConfig::OPTIMIZATION_LEVEL, $values)) {
            return [];
        }

        $level = ScalarCoercion::toInt($values[PhelConfig::OPTIMIZATION_LEVEL]);
        if (in_array($level, self::SUPPORTED_OPTIMIZATION_LEVELS, true)) {
            return [];
        }

        return [ConfigIssue::warning(sprintf(
            'Unknown optimization level %d; supported levels are 0, 1, 2',
            $level,
        ))];
    }

    /**
     * Flags config values whose type does not match the expected shape. Such
     * values are otherwise silently coerced to a default (e.g. a `src-dirs`
     * string becomes an empty list), discarding the user's intent without a
     * trace.
     *
     * @param array<string, mixed> $values
     *
     * @return list<ConfigIssue>
     */
    private function typeMismatchWarnings(array $values): array
    {
        $listKeys = [
            PhelConfig::SRC_DIRS,
            PhelConfig::TEST_DIRS,
            PhelConfig::FORMAT_DIRS,
            PhelConfig::IGNORE_WHEN_BUILDING,
            PhelConfig::NO_CACHE_WHEN_BUILDING,
        ];

        $issues = [];
        foreach ($listKeys as $key) {
            if (array_key_exists($key, $values) && !is_array($values[$key])) {
                $issues[] = ConfigIssue::warning(sprintf(
                    "Config key '%s' should be a list of strings, got %s; the value will be ignored",
                    $key,
                    get_debug_type($values[$key]),
                ));
            }
        }

        if (array_key_exists(PhelConfig::OPTIMIZATION_LEVEL, $values)
            && !is_numeric($values[PhelConfig::OPTIMIZATION_LEVEL])
        ) {
            $issues[] = ConfigIssue::warning(sprintf(
                "Config key '%s' should be an integer, got %s; the value will be ignored",
                PhelConfig::OPTIMIZATION_LEVEL,
                get_debug_type($values[PhelConfig::OPTIMIZATION_LEVEL]),
            ));
        }

        return $issues;
    }
}
