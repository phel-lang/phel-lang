<?php

declare(strict_types=1);

return [
    'src-dirs' => [
        '../../../../../src/phel/',
        'Fixtures/',
    ],
    'test-dirs' => [],
    'vendor' => '../../../../../../vendor/',
    // Force a fresh compile on every run: on a warm compiled-code cache the
    // cached file is required directly and cache-mode ns emission contains
    // no runtime dependency loading (frameworks preload deps in order), so
    // the require path under test would never fire.
    'enable-compiled-code-cache' => false,
];
