<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phar;
use Phel\Config\PhelConfig;
use Phel\Filesystem\FilesystemFacade;
use Phel\Run\RunFacade;
use RuntimeException;

use function dirname;
use function getcwd;
use function in_array;
use function is_array;
use function is_string;

/**
 * @internal use \Phel instead
 */
class Phel
{
    public const string PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private const string PHEL_CONFIG_LOCAL_FILE_NAME = 'phel-config-local.php';

    /**
     * Use sys_get_temp_dir() by default.
     * This can be overridden with the env variable: GACELA_CACHE_DIR=/tmp...
     *
     * @see https://github.com/gacela-project/gacela/pull/322
     */
    private const string FILE_CACHE_DIR = '';

    private static ?PhelConfig $autoDetectedConfig = null;

    public static function bootstrap(string $projectRootDir, array|string|null $argv = null): void
    {
        if ($argv !== null) {
            self::updateGlobalArgv($argv);
        }

        if (str_starts_with(__FILE__, 'phar://')) {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new RuntimeException('Unable to determine current working directory.');
            }

            $currentDirConfig = $cwd . '/' . self::PHEL_CONFIG_FILE_NAME;
            if (file_exists($currentDirConfig)) {
                $projectRootDir = $cwd;
            } elseif (file_exists(dirname(Phar::running(false)) . '/' . self::PHEL_CONFIG_FILE_NAME)) {
                $projectRootDir = dirname(Phar::running(false));
            } else {
                $projectRootDir = Phar::running(true);
            }
        }

        // Zero-config support: auto-detect project structure if no config file exists
        $configPath = $projectRootDir . '/' . self::PHEL_CONFIG_FILE_NAME;
        if (!file_exists($configPath)) {
            self::$autoDetectedConfig = self::detectProjectStructure($projectRootDir);
        }

        Gacela::bootstrap($projectRootDir, self::configFn());
    }

    /**
     * Auto-detect project structure and return a sensible default configuration.
     * This enables zero-config usage for projects following conventional layouts.
     */
    public static function detectProjectStructure(string $projectRootDir): PhelConfig
    {
        $config = new PhelConfig();

        // Detect source directories (prefer src/phel over src)
        $srcDirs = [];
        if (is_dir($projectRootDir . '/src/phel')) {
            $srcDirs[] = 'src/phel';
        } elseif (is_dir($projectRootDir . '/src')) {
            $srcDirs[] = 'src';
        }

        if ($srcDirs !== []) {
            $config->setSrcDirs($srcDirs);
            $config->setExportFromDirectories($srcDirs);
        }

        // Detect test directories (prefer tests/phel over tests)
        $testDirs = [];
        if (is_dir($projectRootDir . '/tests/phel')) {
            $testDirs[] = 'tests/phel';
        } elseif (is_dir($projectRootDir . '/tests')) {
            $testDirs[] = 'tests';
        }

        if ($testDirs !== []) {
            $config->setTestDirs($testDirs);
        }

        // Set format dirs based on detected structure
        $formatDirs = array_merge($srcDirs, $testDirs);
        if ($formatDirs !== []) {
            $config->setFormatDirs($formatDirs);
        }

        // Detect vendor directory
        if (is_dir($projectRootDir . '/vendor')) {
            $config->setVendorDir('vendor');
        }

        return $config;
    }

    /**
     * Get the auto-detected config (for use by Gacela config provider).
     */
    public static function getAutoDetectedConfig(): ?PhelConfig
    {
        return self::$autoDetectedConfig;
    }

    /**
     * This function helps to unify the running execution for a custom phel project.
     *
     * @param list<string>|string|null $argv
     */
    public static function run(string $projectRootDir, string $namespace, array|string|null $argv = null): void
    {
        self::bootstrap($projectRootDir, $argv);

        $runFacade = new RunFacade();
        $runFacade->runNamespace($namespace);

        Gacela::get(FilesystemFacade::class)?->clearAll();
    }

    /**
     * @return Closure(GacelaConfig):void
     */
    public static function configFn(): callable
    {
        return static function (GacelaConfig $config): void {
            $config->enableFileCache(self::FILE_CACHE_DIR);
            // If we have auto-detected config (no phel-config.php exists), use it
            $autoConfig = self::getAutoDetectedConfig();
            if ($autoConfig instanceof PhelConfig) {
                // Register the auto-detected config as inline config
                $config->addAppConfigKeyValues($autoConfig->jsonSerialize());
            } else {
                // Normal config file loading
                $config->addAppConfig(self::PHEL_CONFIG_FILE_NAME, self::PHEL_CONFIG_LOCAL_FILE_NAME);
            }
        };
    }

    /**
     * Reset the auto-detected config (useful for testing).
     */
    public static function resetAutoDetectedConfig(): void
    {
        self::$autoDetectedConfig = null;
    }

    /**
     * @param list<string>|string $argv
     */
    private static function updateGlobalArgv(array|string $argv): void
    {
        $updateGlobals = static function (array $list): void {
            foreach (array_filter($list) as $value) {
                if (!in_array($value, $GLOBALS['argv'], true)) {
                    $GLOBALS['argv'][] = $value;
                }
            }
        };

        if (is_string($argv) && $argv !== '') {
            $updateGlobals(explode(' ', $argv));
        } elseif (is_array($argv) && $argv !== []) {
            $updateGlobals($argv);
        }
    }
}
