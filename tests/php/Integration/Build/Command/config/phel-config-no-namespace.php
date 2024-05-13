<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\PhelBuildConfig;

return (new PhelConfig())
    ->setSrcDirs([__DIR__ . '/../'])
    ->setVendorDir('')
    ->setBuildConfig((new PhelBuildConfig())
        ->setDestDir('out'))
    ->setIgnoreWhenBuilding(['local.phel', 'failing.phel'])
;
