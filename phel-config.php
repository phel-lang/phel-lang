<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;

return PhelConfig::forProject('phel\core', ProjectLayout::Nested)
    ->withIgnoreWhenBuilding(['src/phel/local.phel']);
