<?php

declare(strict_types=1);

return (new Phel\Config\PhelConfig())
    ->setSrcDirs(['src/phel'])
    ->setTestDirs(['tests/phel'])
    ->setVendorDir('vendor')
    ->setErrorLogFile('/tmp/phel-error.log')
    ->setIgnoreWhenBuilding(['src/phel/local.phel'])
    ->setNoCacheWhenBuilding([])
    ->setKeepGeneratedTempFiles(false)
    ->setTempDir(sys_get_temp_dir() . '/phel/tmp')
    ->setCacheDir(sys_get_temp_dir() . '/phel/cache')
    ->setFormatDirs(['src', 'tests'])
    ->setEnableAsserts(true)
    ->setBuildConfig((new Phel\Config\PhelBuildConfig())
        ->setMainPhelNamespace('phel\core')
        ->setMainPhpPath('out/index.php'))
    ->setExportConfig((new Phel\Config\PhelExportConfig())
        ->setFromDirectories(['src/phel'])
        ->setNamespacePrefix('PhelGenerated')
        ->setTargetDirectory('src/PhelGenerated'))
;
