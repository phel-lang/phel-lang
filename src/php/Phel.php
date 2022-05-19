<?php

declare(strict_types=1);

namespace Phel;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\RunFacade;

final class Phel
{
    /**
     * This is a helper function to unify the run execution for a custom phel project.
     */
    public static function run(string $projectRootDir, string $namespace): void
    {
        $configFn = static function (GacelaConfig $config): void {
            $config->addAppConfig('phel-config.php', 'phel-config-local.php');
        };

        Gacela::bootstrap($projectRootDir, $configFn);

        $runFacade = new RunFacade();
        $runFacade->runNamespace($namespace);
    }
}
