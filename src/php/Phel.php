<?php

declare(strict_types=1);

namespace Phel;

use BadMethodCallException;
use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phar;
use Phel\Filesystem\FilesystemFacade;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Registry;
use Phel\Run\RunFacade;

use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function method_exists;
use function sprintf;

/**
 * @internal use \Phel instead
 *
 * @method static void clear()
 * @method static void addDefinition(string $ns, string $name, mixed $value, ?PersistentMapInterface $metaData = null)
 * @method static bool hasDefinition(string $ns, string $name)
 * @method static mixed getDefinition(string $ns, string $name)
 * @method static null|PersistentMapInterface getDefinitionMetaData(string $ns, string $name)
 * @method static array<string, mixed> getDefinitionInNamespace(string $ns)
 * @method static list<string> getNamespaces()
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

    /**
     * Proxy undefined static method calls the registry instance.
     *
     * @param  list<mixed>  $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $registry = Registry::getInstance();
        if (method_exists($registry, $name)) {
            return $registry->$name(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method "%s" does not exist', $name));
    }

    public static function &getDefinitionReference(string $ns, string $name): mixed
    {
        return Registry::getInstance()->getDefinitionReference($ns, $name);
    }

    public static function bootstrap(string $projectRootDir, array|string|null $argv = null): void
    {
        if ($argv !== null) {
            self::updateGlobalArgv($argv);
        }

        if (str_starts_with(__FILE__, 'phar://')) {
            $currentDirConfig = getcwd() . '/' . self::PHEL_CONFIG_FILE_NAME;
            if (file_exists($currentDirConfig)) {
                $projectRootDir = (string) getcwd();
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
     * @param  list<string>|string|null  $argv
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
     * @param  list<string>|string  $argv
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
