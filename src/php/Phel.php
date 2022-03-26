<?php

declare(strict_types=1);

namespace Phel;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Gacela;
use Gacela\Framework\Setup\SetupGacela;
use Phel\Run\RunFacade;

final class Phel
{
    /**
     * This is a helper function to unify the run execution for a custom phel project.
     */
    public static function run(string $projectRootDir, string $namespace): void
    {
        $setupGacela = (new SetupGacela())
            ->setConfig(static function (ConfigBuilder $configBuilder): void {
                $configBuilder->add('phel-config.php', 'phel-config-local.php');
            });

        Gacela::bootstrap($projectRootDir, $setupGacela);

        $runFacade = new RunFacade();
        $runFacade->runNamespace($namespace);
    }
}
