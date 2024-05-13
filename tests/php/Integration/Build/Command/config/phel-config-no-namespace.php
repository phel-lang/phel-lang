<?php

declare(strict_types=1);

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->setSrcDirs([__DIR__ . '/../'])
    ->setVendorDir('')
    ->setBuildConfig((new PhelBuildConfig())
        ->setDestDir('out'))
    ->setIgnoreWhenBuilding(['local.phel', 'failing.phel'])
;
