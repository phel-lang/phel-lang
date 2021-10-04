<?php

declare(strict_types=1);

namespace Phel;

use Gacela\Framework\Gacela;
use Phel\Run\RunFacade;

final class Phel
{
    /**
     * This is a helper function to unify the run execution for a custom phel project.
     */
    public static function run(string $projectRootDir, string $namespace): void
    {
        Gacela::bootstrap($projectRootDir, [
            'config' => [
                'type' => 'php',
                'path' => 'phel-config.php',
                'path_local' => 'phel-config_local.php',
            ],
        ]);

        $runFacade = new RunFacade();
        $runFacade->runNamespace($namespace);
    }
}
