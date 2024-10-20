<?php

declare(strict_types=1);

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->setSrcDirs([__DIR__ . '/../'])
    ->setVendorDir('')
    ->setBuildConfig((new PhelBuildConfig())
        ->setDestDir('out2'))
    ->setIgnoreWhenBuilding(['local.phel', 'failing.phel'])
;
