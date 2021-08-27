<?php

declare(strict_types=1);

use Phel\Config\ExportConfiguration;
use Phel\Config\ProjectConfiguration;

return (new ProjectConfiguration())
    ->setExportConfiguration((new ExportConfiguration())
        ->setDirectories('src')
        ->setNamespacePrefix('PhelGenerated')
        ->setTargetDirectory('./tests/php/Integration/Command/Export/PhelGenerated'));
