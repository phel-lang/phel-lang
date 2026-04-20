<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return PhelConfig::forProject('phel\core')
    ->useNestedLayout()
    ->setIgnoreWhenBuilding(['src/phel/local.phel']);
