<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs([__DIR__ . '/src'])
    ->withExportFromDirectories(['src'])
    ->withExportNamespacePrefix('PhelTest\Integration\Interop\Command\Export\PhelGenerated')
    ->withExportTargetDirectory(__DIR__ . '/PhelGenerated');
