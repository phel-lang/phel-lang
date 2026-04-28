<?php

declare(strict_types=1);

namespace Phel\Run\Runtime;

use Phel\Build\BuildFacade;

final class PhelSourceLoader
{
    public static function load(string $sourcePath): void
    {
        BuildFacade::enableBuildMode();

        try {
            new BuildFacade()->evalFile($sourcePath);
        } finally {
            BuildFacade::disableBuildMode();
        }
    }
}
