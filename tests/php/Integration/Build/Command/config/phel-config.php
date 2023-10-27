<?php

declare(strict_types=1);

use Phel\Config\PhelOutConfig;

return (new \Phel\Config\PhelConfig())
    ->setSrcDirs([__DIR__ . '/../'])
    ->setVendorDir('')
    ->setOut((new PhelOutConfig())
        ->setDestDir('out')
        ->setMainPhelNamespace('test-ns\hello')
        ->setMainPhpFilename('main'))
    ->setIgnoreWhenBuilding(['local.phel', 'failing.phel'])
;
