<?php

declare(strict_types=1);

use Phel\ProjectConfiguration;

return (new ProjectConfiguration())
    ->setExportDirectories('src')
    ->setExportNamespacePrefix('PhelGenerated')
    ->setExportTargetDirectory('./tests/php/Integration/Command/Export/PhelGenerated')
    ->toArray();
