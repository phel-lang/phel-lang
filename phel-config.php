<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;

return PhelConfig::forProject(ProjectLayout::Nested)
    ->withMainPhelNamespace('phel.core')
    // Gacela's module discovery class_exists()-walks the whole project root.
    // Under tests/ that loads PHPUnit classes standalone, which fatals; the
    // Gacela modules all live in src/php anyway (#2787).
    ->withAppModulePaths(['src/php'])
    ->withIgnoreWhenBuilding(['src/phel/local.phel']);
