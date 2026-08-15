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
    ->withIgnoreWhenBuilding(['src/phel/local.phel'])
    // `optimizationLevel` defaults to 0, so nothing exercised `CallInliner` or
    // `TailCallRewriter` until #3126 raised it by hand and found five failing
    // core tests. Reading it from the environment gives CI a job at -O2 and
    // makes a local repro one variable: `PHEL_OPTIMIZATION_LEVEL=2 ./bin/phel test`.
    ->withOptimizationLevel((int) (getenv('PHEL_OPTIMIZATION_LEVEL') ?: '0'));
