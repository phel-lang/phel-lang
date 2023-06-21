<?php

declare(strict_types=1);

return (new \Phel\Config\PhelConfig())
    ->setSrcDirs(['src/phel'])
    ->setTestDirs(['tests/phel'])
    ->setVendorDir('vendor')
    ->setOutDir('out')
    ->setOutMainNs('')
    ->setOutMainFilename('')
    ->setExport((new \Phel\Config\PhelExportConfig())
        ->setDirectories(['src/phel'])
        ->setNamespacePrefix('PhelGenerated')
        ->setTargetDirectory('src/PhelGenerated'))
    ->setIgnoreWhenBuilding(['src/phel/local.phel'])
    ->setKeepGeneratedTempFiles(false)
    ->setFormatDirs(['src', 'tests'])
;
