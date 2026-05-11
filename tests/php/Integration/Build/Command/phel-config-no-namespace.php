<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/../'])
    ->withVendorDir('')
    ->withBuildDestDir('out')
    ->withIgnoreWhenBuilding(['local.phel', 'failing.phel']);
