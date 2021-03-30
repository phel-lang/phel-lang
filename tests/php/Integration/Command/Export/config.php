<?php

declare(strict_types=1);

use Gacela\Config;
use Phel\Command\CommandConfig;
use Phel\Interop\InteropConfig;

return [
    CommandConfig::DEFAULT_TEST_DIRECTORIES => ['tests/phel'],
    InteropConfig::EXPORT_DIRECTORIES => ['src'],
    InteropConfig::EXPORT_TARGET_DIRECTORY => Config::getApplicationRootDir() . '/PhelGenerated',
    InteropConfig::EXPORT_NAMESPACE_PREFIX => 'PhelGenerated',
];
