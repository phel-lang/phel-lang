<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phar;
use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;
use Phel\Filesystem\FilesystemFacade;
use Phel\Run\RunFacade;
use RuntimeException;

use function dirname;
use function getcwd;
use function in_array;

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

    /**
     * Set up Phel runtime argv and program globals.
     * This normalizes argument handling so argv contains only user arguments.
     *
     * @param string       $program The script path or namespace being executed
     * @param list<string> $argv    User arguments (without script name)
     */
    public static function setupRuntimeArgs(string $program, array $argv): void
    {
        $GLOBALS['__phel_program'] = $program;
        $GLOBALS['__phel_argv'] = $argv;
    }

    /**
     * Get the current program (script path or namespace).
     */
    public static function getProgram(): string
    {
        return $GLOBALS['__phel_program'] ?? '';
    }

    /**
     * Get user arguments (without script name).
     *
     * @return list<string>
     */
    public static function getArgv(): array
    {
        return $GLOBALS['__phel_argv'] ?? [];
    }

    public static function bootstrap(string $projectRootDir, ?array $argv = null): void
    {
        if ($argv !== null && $argv !== []) {
            self::updateGlobalArgv($argv);
        }

        if (str_starts_with(__FILE__, 'phar://')) {
            $projectRootDir = self::resolvePharProjectRoot();
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

        // Single scan of top-level directory to minimize syscalls
        $topLevel = @scandir($projectRootDir) ?: [];
        $hasSrc = in_array('src', $topLevel, true);
        $hasTests = in_array('tests', $topLevel, true);
        $hasVendor = in_array('vendor', $topLevel, true);

        // Check for conventional layout (src/phel, tests/phel) only if parent exists
        $hasSrcPhel = $hasSrc && is_dir($projectRootDir . '/src/phel');
        $hasTestsPhel = $hasTests && is_dir($projectRootDir . '/tests/phel');

        // Determine layout based on detected structure
        if ($hasSrcPhel || $hasTestsPhel) {
            $config->useLayout(ProjectLayout::Conventional);
        } elseif ($hasSrc || $hasTests) {
            $config->useLayout(ProjectLayout::Flat);
        }

        if ($hasVendor) {
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
     * @param list<string>|null $argv User arguments (not including program name)
     */
    public static function run(string $projectRootDir, string $namespace, ?array $argv = null): void
    {
        // Set up normalized runtime args (program + user-only argv)
        self::setupRuntimeArgs($namespace, $argv ?? []);

        self::bootstrap($projectRootDir);

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
     * Resolve the project root directory when running from a PHAR.
     * Priority: 1) CWD with config, 2) PHAR directory with config, 3) Inside PHAR
     */
    private static function resolvePharProjectRoot(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Unable to determine current working directory.');
        }

        // Check CWD first
        if (file_exists($cwd . '/' . self::PHEL_CONFIG_FILE_NAME)) {
            return $cwd;
        }

        // Check PHAR's directory
        $pharDir = dirname(Phar::running(false));
        if (file_exists($pharDir . '/' . self::PHEL_CONFIG_FILE_NAME)) {
            return $pharDir;
        }

        // Fall back to inside the PHAR
        return Phar::running(true);
    }

    /**
     * @param list<string> $argv
     */
    private static function updateGlobalArgv(array $argv): void
    {
        foreach (array_filter($argv) as $value) {
            if (!in_array($value, $GLOBALS['argv'], true)) {
                $GLOBALS['argv'][] = $value;
            }
        }
    }
}
