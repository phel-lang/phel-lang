<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

// Same project as phel-config-load-e2e.php but with the compiled-code cache
// off, the configuration that made a downstream build drop its `(load ...)`
// secondaries (#2648).
return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src-load-e2e'])
    ->withVendorDir('')
    ->withMainPhelNamespace('loade2e\core')
    ->withMainPhpPath('out-load-e2e-no-cache/main.php')
    ->withBuildDestDir('out-load-e2e-no-cache')
    ->withEnableCompiledCodeCache(false)
    ->withIgnoreWhenBuilding([]);
