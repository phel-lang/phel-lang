<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;

return (new PhelConfig())
    ->setSrcDirs([__DIR__ . '/../'])
    ->setTestDirs([])
    ->setVendorDir('')
    ->setExportConfig((new PhelExportConfig())
        ->setFromDirectories(['src'])
        ->setNamespacePrefix('PhelGenerated')
        ->setTargetDirectory(__DIR__ . '/../PhelGenerated'))
;
