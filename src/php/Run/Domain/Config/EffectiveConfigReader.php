<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Config;

use Gacela\Framework\Config\Config;
use Phel\Config\PhelConfig;
use Phel\Shared\PhelProjectDirectory;

use function array_key_exists;

use function getenv;

use const DIRECTORY_SEPARATOR;

/**
 * Reads the effective, merged Phel configuration that the running CLI actually
 * sees, together with where it came from (config file, local override, env).
 *
 * The values come from Gacela's merged config, so they already reflect
 * `phel-config.php`, the optional `phel-config-local.php` override, and any
 * auto-detected zero-config defaults.
 */
final class EffectiveConfigReader
{
    private const string CONFIG_FILE_NAME = 'phel-config.php';

    private const string LOCAL_CONFIG_FILE_NAME = 'phel-config-local.php';

    /**
     * Canonical key order, mirroring PhelConfig::jsonSerialize().
     *
     * @var list<string>
     */
    private const array KEY_ORDER = [
        PhelConfig::SRC_DIRS,
        PhelConfig::TEST_DIRS,
        PhelConfig::VENDOR_DIR,
        PhelConfig::ERROR_LOG_FILE,
        PhelConfig::BUILD_CONFIG,
        PhelConfig::EXPORT_CONFIG,
        PhelConfig::IGNORE_WHEN_BUILDING,
        PhelConfig::NO_CACHE_WHEN_BUILDING,
        PhelConfig::KEEP_GENERATED_TEMP_FILES,
        PhelConfig::TEMP_DIR,
        PhelConfig::FORMAT_DIRS,
        PhelConfig::ASSERTS_ENABLED,
        PhelConfig::WARN_DEPRECATIONS,
        PhelConfig::ENABLE_NAMESPACE_CACHE,
        PhelConfig::ENABLE_COMPILED_CODE_CACHE,
        PhelConfig::CACHE_DIR,
        PhelConfig::PHEL_DIR,
        PhelConfig::OPTIMIZATION_LEVEL,
    ];

    public function read(): EffectiveConfigResult
    {
        $config = Config::getInstance();
        $root = $config->getAppRootDir();
        $allValues = $config->getAllValues();

        $configPath = $root . DIRECTORY_SEPARATOR . self::CONFIG_FILE_NAME;
        $localPath = $root . DIRECTORY_SEPARATOR . self::LOCAL_CONFIG_FILE_NAME;

        $phelDirEnv = getenv(PhelProjectDirectory::DIR_ENV);

        return new EffectiveConfigResult(
            projectRoot: $root,
            configFilePath: $configPath,
            configFileExists: file_exists($configPath),
            localConfigFilePath: $localPath,
            localConfigFileExists: file_exists($localPath),
            phelDirEnv: $phelDirEnv === false ? null : $phelDirEnv,
            values: $this->orderedValues($allValues),
        );
    }

    /**
     * @param array<string, mixed> $allValues
     *
     * @return array<string, mixed>
     */
    private function orderedValues(array $allValues): array
    {
        $ordered = [];
        foreach (self::KEY_ORDER as $key) {
            if (array_key_exists($key, $allValues)) {
                $ordered[$key] = $allValues[$key];
            }
        }

        return $ordered;
    }
}
