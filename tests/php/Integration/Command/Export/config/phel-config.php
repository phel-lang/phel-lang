<?php

declare(strict_types=1);

return [
    'src-dirs' => [
        '/../../../../../src/phel/',
        'src',
    ],
    'test-dirs' => ['empty'],
    'vendor-dir' => 'empty',

    'export' => [
        'directories' => ['src'],
        'namespace-prefix' => 'PhelGenerated',
        'target-directory' => './tests/php/Integration/Command/Export/PhelGenerated',
    ],
];
