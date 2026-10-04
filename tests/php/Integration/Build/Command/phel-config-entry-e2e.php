<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-entry-e2e'])
    ->withVendorDir('')
    ->withMainPhelNamespace('entrye2e\main')
    ->withMainPhpPath('out-entry-e2e/main.php')
    ->withBuildDestDir('out-entry-e2e')
    ->withIgnoreWhenBuilding([]);
