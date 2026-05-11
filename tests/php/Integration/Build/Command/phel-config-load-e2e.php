<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-load-e2e'])
    ->withVendorDir('')
    ->withMainPhelNamespace('loade2e\core')
    ->withMainPhpPath('out-load-e2e/main.php')
    ->withBuildDestDir('out-load-e2e')
    ->withIgnoreWhenBuilding([]);
