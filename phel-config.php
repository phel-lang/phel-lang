<?php

declare(strict_types=1);

return (new \Phel\Config\PhelConfig())
    ->setSrcDirs(['src/phel'])
    ->setTestDirs(['tests/phel'])
    ->setVendorDir('vendor')
    ->setErrorLogFile('data/error.log')
    ->setIgnoreWhenBuilding(['src/phel/local.phel'])
    ->setNoCacheWhenBuilding([])
    ->setKeepGeneratedTempFiles(false)
    ->setFormatDirs(['src', 'tests'])
    ->setBuildConfig((new \Phel\Config\PhelBuildConfig())
        ->setMainPhelNamespace('phel\core')
        ->setMainPhpPath('out/index.php'))
    ->setExportConfig((new \Phel\Config\PhelExportConfig())
        ->setFromDirectories(['src/phel'])
        ->setNamespacePrefix('PhelGenerated')
        ->setTargetDirectory('src/PhelGenerated'))
;
