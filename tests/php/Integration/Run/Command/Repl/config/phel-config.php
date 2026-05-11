<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return new PhelConfig()
    ->withSrcDirs(['../../../../../../../src/phel/'])
    ->withTestDirs(['Fixtures'])
    ->withVendorDir('')
    ->withErrorLogFile('data/error.log');
