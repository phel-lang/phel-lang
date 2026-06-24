<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-failing'])
    ->withVendorDir('')
    ->withBuildDestDir('out-failing')
    ->withOptimizationLevel(0);
