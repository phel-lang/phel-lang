<?php

declare(strict_types=1);

use Phel\Config\ExportConfiguration;
use Phel\Config\ProjectConfiguration;
use Phel\Config\TestConfiguration;

return (new ProjectConfiguration())
    ->setTestConfiguration((new TestConfiguration())
        ->setDirectories('tests/phel/'))
    ->setExportConfiguration((new ExportConfiguration())
        ->setDirectories(['src/phel'])
        ->setNamespacePrefix('PhelGenerated')
        ->setTargetDirectory('src/PhelGenerated'));
