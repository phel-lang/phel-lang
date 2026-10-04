<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-absolute-load-e2e'])
    ->withVendorDir('')
    ->withMainPhelNamespace('abse2e.main')
    ->withMainPhpPath('out-absolute-load-e2e/main.php')
    ->withBuildDestDir('out-absolute-load-e2e')
    ->withIgnoreWhenBuilding([]);
