<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Composer\Autoload\ClassLoader;
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

        // Load the host project's Composer autoloader when running the PHAR inside another project.
        // Skip when the resolved project root is actually the PHAR's own directory (would duplicate autoloader).
        $runningInPhar = str_starts_with(__FILE__, 'phar://');
        $pharDir = $runningInPhar ? dirname(Phar::running(false)) : null;

        if (!is_file($projectRootDir)) {
            $projectAutoloader = $projectRootDir . '/vendor/autoload.php';

            // Determine if the project root is the same as the PHAR directory
            $isSameRootAsPhar = false;
            if ($runningInPhar && $pharDir !== null) {
                try {
                    $projectRootReal = realpath($projectRootDir);
                    $pharDirReal = realpath($pharDir);
                    $isSameRootAsPhar = $projectRootReal !== false
                        && $pharDirReal !== false
                        && $projectRootReal === $pharDirReal;
                } catch (Exception) {
                    // If realpath fails, assume they're different
                    $isSameRootAsPhar = false;
                }
            }

            // Skip loading if Composer autoloader is already initialized
            // This prevents duplicate class declaration errors when the PHAR's
            // autoloader is already loaded (e.g., when running PHAR from the build directory)
            if (!$isSameRootAsPhar && file_exists($projectAutoloader) && !str_starts_with($projectAutoloader, 'phar://') && !class_exists(ClassLoader::class, false)) {
                // Also check if this specific project's autoloader is already loaded
                // to handle cases where a different autoloader with the same classes might be included
                $projectAutoloaderReal = realpath($projectAutoloader);
                $autoloaderAlreadyLoaded = false;
                if ($projectAutoloaderReal !== false) {
                    foreach (get_included_files() as $includedFile) {
                        $includedFileReal = realpath($includedFile);
                        if ($includedFileReal !== false && $includedFileReal === $projectAutoloaderReal) {
                            $autoloaderAlreadyLoaded = true;
                            break;
                        }
                    }
                }

                if (!$autoloaderAlreadyLoaded) {
                    /** @psalm-suppress UnresolvableInclude */
                    require_once $projectAutoloader;
                }
            }
        }

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
}
