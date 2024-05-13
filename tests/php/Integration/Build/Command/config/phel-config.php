<?php

declare(strict_types=1);

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->setSrcDirs([__DIR__ . '/../'])
    ->setVendorDir('')
    ->setBuildConfig((new PhelBuildConfig())
        ->setMainPhelNamespace('test-ns\hello')
        ->setMainPhpPath('out/main.php'))
    ->setIgnoreWhenBuilding(['local.phel', 'failing.phel'])
    ->setNoCacheWhenBuilding(['no-cache.phel'])
;
