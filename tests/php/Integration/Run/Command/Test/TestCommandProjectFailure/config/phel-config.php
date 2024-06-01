<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->setSrcDirs(['../../../../../../../src/phel/'])
    ->setTestDirs(['Fixtures'])
    ->setVendorDir('')
    ->setErrorLogFile('data/error.log')
;
