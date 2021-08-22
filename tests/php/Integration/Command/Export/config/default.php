<?php

declare(strict_types=1);

use Phel\Interop\InteropConfig;

return [
    InteropConfig::EXPORT_DIRECTORIES => [
        'src',
    ],
    InteropConfig::EXPORT_NAMESPACE_PREFIX => 'PhelGenerated',
    InteropConfig::EXPORT_TARGET_DIRECTORY => './tests/php/Integration/Command/Export/PhelGenerated',
];
