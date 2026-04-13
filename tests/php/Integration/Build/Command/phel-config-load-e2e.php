<?php

declare(strict_types=1);

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->setSrcDirs([__DIR__ . '/src-load-e2e'])
    ->setVendorDir('')
    ->setBuildConfig((new PhelBuildConfig())
        ->setMainPhelNamespace('loade2e\core')
        ->setMainPhpPath('out-load-e2e/main.php')
        ->setDestDir('out-load-e2e'))
    ->setIgnoreWhenBuilding([])
;
