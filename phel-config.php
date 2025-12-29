<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

// Minimal configuration - all other settings use sensible defaults.
// The conventional layout (src/phel, tests/phel) is used by default.
return PhelConfig::forProject('phel\core')
    ->setIgnoreWhenBuilding(['src/phel/local.phel']);
