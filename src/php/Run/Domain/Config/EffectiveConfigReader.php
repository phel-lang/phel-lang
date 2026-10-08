<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Config;

use Gacela\Framework\Config\Config;
use Phel\Config\PhelConfig;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\OptimizationLevel;
use Phel\Shared\PhelProjectDirectory;

use function array_key_exists;
use function file_exists;

use const DIRECTORY_SEPARATOR;

/**
 * Reads the configuration a command of this process runs with, together with
 * where it came from (config file, local override, env).
 *
 * The values start from Gacela's merged config, so they reflect
 * `phel-config.php`, the optional `phel-config-local.php` override and any
 * auto-detected zero-config defaults. The settings an env var or flag can
 * beat are then asked of the code that applies them when a command runs, so
 * this report cannot drift from it (#3525).
 *
 * @internal
 */
final readonly class EffectiveConfigReader
{
    private const string CONFIG_FILE_NAME = 'phel-config.php';

    private const string LOCAL_CONFIG_FILE_NAME = 'phel-config-local.php';

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function read(): EffectiveConfigResult
    {
        $config = Config::getInstance();
        $root = $config->getAppRootDir();

        $configPath = $root . DIRECTORY_SEPARATOR . self::CONFIG_FILE_NAME;
        $localPath = $root . DIRECTORY_SEPARATOR . self::LOCAL_CONFIG_FILE_NAME;

        $configured = $this->configuredValues($config->getAllValues());

        return new EffectiveConfigResult(
            projectRoot: $root,
            configFilePath: $configPath,
            configFileExists: file_exists($configPath),
            localConfigFilePath: $localPath,
            localConfigFileExists: file_exists($localPath),
            phelDirEnv: PhelProjectDirectory::dirOverride(),
            configuredValues: $configured,
            values: $this->effectiveValues($configured),
        );
    }

    /**
     * Every key of {@see PhelConfig::jsonSerialize()}, in its order. A key the
     * merged config lacks takes the model's default.
     *
     * @param array<string, mixed> $merged
     *
     * @return array<string, mixed>
     */
    private function configuredValues(array $merged): array
    {
        $values = [];
        foreach (new PhelConfig()->jsonSerialize() as $key => $default) {
            $values[$key] = array_key_exists($key, $merged) ? $merged[$key] : $default;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function effectiveValues(array $values): array
    {
        $values[PhelConfig::CACHE_DIR] = PhelProjectDirectory::cacheDirOverride() ?? $values[PhelConfig::CACHE_DIR];
        $values[PhelConfig::PHEL_DIR] = PhelProjectDirectory::dirOverride() ?? $values[PhelConfig::PHEL_DIR];
        $values[PhelConfig::OPTIMIZATION_LEVEL] = OptimizationLevel::resolve($values[PhelConfig::OPTIMIZATION_LEVEL]);
        $values[PhelConfig::WARN_DEPRECATIONS] = $this->compilerFacade->deprecationWarningsEnabled();

        return $values;
    }
}
