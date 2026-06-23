<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Config;

use Phel\Config\PhelConfig;
use Phel\Shared\ScalarCoercion;

use function array_map;

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
    /**
     * @param array<string, mixed> $values the effective merged config, shaped
     *                                     like {@see PhelConfig::jsonSerialize()}
     *
     * @return list<ConfigIssue>
     */
    public function analyze(array $values): array
    {
        return $this->relativePathErrors($values);
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
}
