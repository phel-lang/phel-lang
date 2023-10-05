<?php

declare(strict_types=1);

use Phel\Config\PhelOutConfig;

return (new \Phel\Config\PhelConfig())
    ->setSrcDirs(['../../../../../src/phel/', 'src'])
    ->setVendorDir('')
    ->setOut((new PhelOutConfig())
        ->setDestDir('out'))
    ->setIgnoreWhenBuilding(['local.phel', 'failing.phel'])
;
