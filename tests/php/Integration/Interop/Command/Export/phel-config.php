<?php

declare(strict_types=1);

return [
    'src-dirs' => [
        __DIR__ . '/../',
    ],
//    'test-dirs' => [],
//    'vendor-dir' => '',
    'export' => [
        'directories' => ['src'],
        'namespace-prefix' => 'PhelTest\Integration\Interop\Command\Export\PhelGenerated',
        'target-directory' => __DIR__ . '/PhelGenerated',
    ],
];
