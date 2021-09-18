<?php

declare(strict_types=1);

return [
    "loader" => [
        "phel\\" => "src/phel/",
    ],
    "loader-dev" => [
        "phel\\" => "tests/phel/",
        "tests-phpbench\\" => "tests/php/Benchmark/",
    ],
    'tests' => [
        'tests/phel/',
    ],
    'export' => [
        'directories' => ['src/phel'],
        'namespace-prefix' => 'PhelGenerated',
        'target-directory' => 'src/PhelGenerated',
    ],
];
