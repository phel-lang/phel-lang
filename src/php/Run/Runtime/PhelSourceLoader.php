<?php

declare(strict_types=1);

namespace Phel\Run\Runtime;

use Phel\Build\BuildFacade;

final class PhelSourceLoader
{
    public static function load(string $sourcePath): void
    {
        // Preserve an outer build: `phel build` evaluates a primary with build
        // mode on, and its `(load ...)` secondaries route back here. Forcing
        // build mode off in `finally` would drop it after the first secondary,
        // so the rest would resolve to a precompiled `.php` (e.g. a PHAR-bundled
        // stdlib sibling) instead of being recompiled and harvested into the
        // build output. Restore the prior state instead.
        $wasBuildMode = BuildFacade::isBuildMode();
        BuildFacade::enableBuildMode();

        try {
            new BuildFacade()->evalFile($sourcePath);
        } finally {
            if (!$wasBuildMode) {
                BuildFacade::disableBuildMode();
            }
        }
    }
}
