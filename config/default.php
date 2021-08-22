<?php

declare(strict_types=1);

use Phel\Command\CommandConfig;
use Phel\Interop\InteropConfig;

return [
    "loader" => [
        "phel\\" => "src/phel/",
    ],
    "loader-dev" => [
        "phel\\" => "tests/phel/",
        "tests-phpbench\\" => "tests/php/Benchmark/",
    ],
    CommandConfig::TEST_DIRECTORIES => [
        "tests/phel/",
    ],
    InteropConfig::EXPORT_DIRECTORIES => [
        "src/phel",
    ],
    InteropConfig::EXPORT_NAMESPACE_PREFIX => "PhelGenerated",
    InteropConfig::EXPORT_TARGET_DIRECTORY => "src/PhelGenerated",
];
