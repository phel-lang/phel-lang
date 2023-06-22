<?php

declare(strict_types=1);

use Phel\Config\PhelOutConfig;

return (new \Phel\Config\PhelConfig())
    ->setSrcDirs(['src/phel'])
    ->setTestDirs(['tests/phel'])
    ->setVendorDir('vendor')
    ->setOut((new PhelOutConfig())
        ->setDestDir('out')
        ->setMainPhelNamespace('')
        ->setMainPhpFilename('main'))
    ->setExport((new \Phel\Config\PhelExportConfig())
        ->setDirectories(['src/phel'])
        ->setNamespacePrefix('PhelGenerated')
        ->setTargetDirectory('src/PhelGenerated'))
    ->setIgnoreWhenBuilding(['src/phel/local.phel'])
    ->setKeepGeneratedTempFiles(false)
    ->setFormatDirs(['src', 'tests'])
;
