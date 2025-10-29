<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;

return (new PhelConfig())
    ->setSrcDirs([__DIR__ . '/src'])
    ->setExportConfig((new PhelExportConfig())
        ->setFromDirectories(['src'])
        ->setNamespacePrefix('PhelTest\Integration\Interop\Command\Export\PhelGenerated')
        ->setTargetDirectory(__DIR__ . '/PhelGenerated'));
