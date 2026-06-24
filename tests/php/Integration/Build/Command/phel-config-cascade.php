<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-cascade'])
    ->withVendorDir('')
    ->withBuildDestDir('out-cascade')
    ->withOptimizationLevel(0);
