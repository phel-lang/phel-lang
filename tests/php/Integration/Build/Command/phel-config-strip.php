<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-strip'])
    ->withVendorDir('')
    ->withMainPhelNamespace('stripns\core')
    ->withBuildDestDir('out-strip')
    ->withStripSymbolMeta();
