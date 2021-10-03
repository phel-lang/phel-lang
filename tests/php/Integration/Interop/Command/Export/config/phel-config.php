<?php

declare(strict_types=1);

return [
    'src-dirs' => [
        '../../../../../../src/phel/',
        'src',
    ],
    'test-dirs' => [],
    'vendor-dir' => '',

    'export' => [
        'directories' => ['src'],
        'namespace-prefix' => 'PhelGenerated',
        'target-directory' => __DIR__ . '/../PhelGenerated',
    ],
];
