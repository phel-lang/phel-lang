<?php

declare(strict_types=1);

use Phel\Command\CommandConfig;
use Phel\Interop\InteropConfig;

return [
    // =============
    // CommandConfig
    // =============
    CommandConfig::DEFAULT_TEST_DIRECTORIES => ['./tests/phel'],

    // =============
    // InteropConfig
    // =============
    InteropConfig::EXPORT_TARGET_DIRECTORY => 'src/PhelGenerated',
    InteropConfig::EXPORT_DIRECTORIES => ['src/phel'],
    InteropConfig::EXPORT_NAMESPACE_PREFIX => 'PhelGenerated',
];
