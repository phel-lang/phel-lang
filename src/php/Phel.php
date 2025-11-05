<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Exception;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phar;
use Phel\Filesystem\FilesystemFacade;
use Phel\Run\RunFacade;
use RuntimeException;

use function dirname;
use function file_exists;
use function get_included_files;
use function getcwd;
use function in_array;
use function is_array;
use function is_file;
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

        self::loadHostProjectAutoloader($projectRootDir);

        Gacela::bootstrap($projectRootDir, self::configFn());
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
            $config->addAppConfig(self::PHEL_CONFIG_FILE_NAME, self::PHEL_CONFIG_LOCAL_FILE_NAME);
        };
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

    /**
     * Load the host project's Composer autoloader when running the PHAR inside another project.
     * This allows the PHAR to resolve dependencies from the host project's vendor directory.
     * Different vendor directories have different ComposerAutoloaderInit[hash] classes, so they're safe to load together.
     */
    private static function loadHostProjectAutoloader(string $projectRootDir): void
    {
        // Skip if project root is a file (not a directory)
        if (is_file($projectRootDir)) {
            return;
        }

        $projectAutoloader = $projectRootDir . '/vendor/autoload.php';

        // Skip if running from PHAR and project root is the PHAR's own directory
        if (self::isProjectRootSameasPharDirectory($projectRootDir)) {
            return;
        }

        // Skip if autoloader doesn't exist or is inside PHAR
        if (!file_exists($projectAutoloader) || str_starts_with($projectAutoloader, 'phar://')) {
            return;
        }

        // Skip if this specific autoloader is already loaded
        if (self::isAutoloaderAlreadyLoaded($projectAutoloader)) {
            return;
        }

        /** @psalm-suppress UnresolvableInclude */
        require_once $projectAutoloader;
    }

    /**
     * Check if the project root directory is the same as the PHAR's own directory.
     */
    private static function isProjectRootSameasPharDirectory(string $projectRootDir): bool
    {
        $runningInPhar = str_starts_with(__FILE__, 'phar://');
        if (!$runningInPhar) {
            return false;
        }

        $pharDir = dirname(Phar::running(false));

        try {
            $projectRootReal = realpath($projectRootDir);
            $pharDirReal = realpath($pharDir);

            return $projectRootReal !== false
                && $pharDirReal !== false
                && $projectRootReal === $pharDirReal;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Check if the project's vendor/autoload.php is already loaded.
     */
    private static function isAutoloaderAlreadyLoaded(string $projectAutoloader): bool
    {
        $projectAutoloaderReal = realpath($projectAutoloader);
        if ($projectAutoloaderReal === false) {
            return false;
        }

        foreach (get_included_files() as $includedFile) {
            $includedFileReal = realpath($includedFile);
            if ($includedFileReal !== false && $includedFileReal === $projectAutoloaderReal) {
                return true;
            }
        }

        return false;
    }
}
