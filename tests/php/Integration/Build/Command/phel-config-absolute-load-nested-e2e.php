<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-absolute-load-nested-e2e'])
    ->withVendorDir('')
    ->withMainPhelNamespace('nest.main')
    ->withMainPhpPath('out-absolute-load-nested-e2e/main.php')
    ->withBuildDestDir('out-absolute-load-nested-e2e')
    ->withIgnoreWhenBuilding([]);
