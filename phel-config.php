<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;

return PhelConfig::forProject(ProjectLayout::Nested)
    ->withMainPhelNamespace('phel.core')
    ->withIgnoreWhenBuilding(['src/phel/local.phel']);
