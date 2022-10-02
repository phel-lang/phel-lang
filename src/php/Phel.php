<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\RunFacade;

use function in_array;

final class Phel
{
    public const PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private const PHEL_CONFIG_LOCAL_FILE_NAME = 'phel-config-local.php';

    /**
     * This function helps to unify the running execution for a custom phel project.
     */
    public static function run(string $projectRootDir, string $namespace, string $argv = ''): void
    {
        self::updateGlobalArgv($argv);

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
            $config->addAppConfig(self::PHEL_CONFIG_FILE_NAME, self::PHEL_CONFIG_LOCAL_FILE_NAME);
        };
    }

    private static function updateGlobalArgv(string $argv): void
    {
        if ($argv !== '') {
            foreach (array_filter(explode(' ', $argv)) as $value) {
                if (!in_array($value, $GLOBALS['argv'], true)) {
                    $GLOBALS['argv'][] = $value;
                }
            }
        }
    }
}
