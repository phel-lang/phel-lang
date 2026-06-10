<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-optimization'])
    ->withVendorDir('')
    ->withBuildDestDir('out-optimization')
    ->withOptimizationLevel(2);
