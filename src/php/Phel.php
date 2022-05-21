<?php

declare(strict_types=1);

namespace Phel;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\RunFacade;

final class Phel
{
    public const PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private const PHEL_CONFIG_LOCAL_FILE_NAME = 'phel-config-local.php';

    /**
     * This function helps to unify the running execution for a custom phel project.
     */
    public static function run(string $projectRootDir, string $namespace): void
    {
        Gacela::bootstrap($projectRootDir, self::configFn());

        $runFacade = new RunFacade();
        $runFacade->runNamespace($namespace);
    }

    /**
     * @return callable(GacelaConfig):void
     */
    public static function configFn(): callable
    {
        return static function (GacelaConfig $config): void {
            $config->addAppConfig(self::PHEL_CONFIG_FILE_NAME, self::PHEL_CONFIG_LOCAL_FILE_NAME);
        };
    }
}
