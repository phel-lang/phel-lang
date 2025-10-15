<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use FilesystemIterator;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phar;
use Phel\Filesystem\FilesystemFacade;
use Phel\Run\RunFacade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function array_filter;
use function dirname;
use function explode;
use function file_exists;
use function getcwd;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function rmdir;
use function rtrim;
use function str_starts_with;
use function unlink;

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
    private const string INTERNAL_FILE_CACHE_DIR = '';

    private const string COMPILED_PHEL_CACHE_DIR = '.phel-cache';

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
            $config->enableFileCache(self::INTERNAL_FILE_CACHE_DIR);
            $config->addAppConfig(self::PHEL_CONFIG_FILE_NAME, self::PHEL_CONFIG_LOCAL_FILE_NAME);
        };
    }

    public static function cacheClear(string $projectRootDir): void
    {
        $cacheDir = rtrim($projectRootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::COMPILED_PHEL_CACHE_DIR;

        if (!is_dir($cacheDir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($cacheDir);
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
