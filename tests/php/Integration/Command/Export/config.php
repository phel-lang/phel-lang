<?php

declare(strict_types=1);

use Gacela\Config;
use Phel\Command\CommandConfig;
use Phel\Interop\InteropConfig;

$config[CommandConfig::DEFAULT_TEST_DIRECTORIES] = ['tests/phel'];

$config[InteropConfig::EXPORT_DIRECTORIES] = ['src'];
$config[InteropConfig::EXPORT_TARGET_DIRECTORY] = Config::$applicationRootDir . '/PhelGenerated';
$config[InteropConfig::EXPORT_NAMESPACE_PREFIX] = 'PhelGenerated';
