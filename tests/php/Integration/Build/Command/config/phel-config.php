<?php

declare(strict_types=1);

return [
    'src-dirs' => [
        '../../../../../src/phel/',
        'src',
    ],
    'out-dir' => 'out',
    'out-main-ns' => 'test-ns\hello',
    'out-main-filename' => 'main',
    'vendor-dir' => '',
    'ignore-when-building' => [
        'local.phel',
        'failing.phel',
    ],
];
