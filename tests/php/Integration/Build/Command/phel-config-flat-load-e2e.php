<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-flat-load-e2e'])
    ->withVendorDir('')
    ->withMainPhelNamespace('flate2e.main')
    ->withMainPhpPath('out-flat-load-e2e/main.php')
    ->withBuildDestDir('out-flat-load-e2e')
    ->withIgnoreWhenBuilding([]);
