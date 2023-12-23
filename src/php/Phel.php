<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\RunFacade;

use function in_array;
use function is_array;
use function is_string;

final class Phel
{
    public const PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private const PHEL_CONFIG_LOCAL_FILE_NAME = 'phel-config-local.php';

    private const FILE_CACHE_DIR = 'data/.cache';

    /**
     * This function helps to unify the running execution for a custom phel project.
     *
     * @param list<string>|string|null $argv
     */
    public static function run(string $projectRootDir, string $namespace, array|string $argv = null): void
    {
        if ($argv !== null) {
            self::updateGlobalArgv($argv);
        }

        Gacela::bootstrap($projectRootDir, self::configFn());

        $runFacade = new RunFacade();
        $runFacade->runNamespace($namespace);
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
