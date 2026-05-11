<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src'])
    ->withVendorDir('')
    ->withMainPhelNamespace('test-ns\hello')
    ->withMainPhpPath('out/main.php')
    ->withIgnoreWhenBuilding(['local.phel', 'failing.phel'])
    ->withNoCacheWhenBuilding(['no-cache.phel']);
