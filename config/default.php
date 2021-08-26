<?php

declare(strict_types=1);

use Phel\ProjectConfiguration;

return (new ProjectConfiguration())
    ->setTestsDirectories('tests/phel/')
    ->setExportDirectories(['src/phel'])
    ->setExportNamespacePrefix('PhelGenerated')
    ->setExportTargetDirectory('src/PhelGenerated')
    ->toArray();
