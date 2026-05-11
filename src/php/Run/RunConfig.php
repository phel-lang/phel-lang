<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractConfig;

final class RunConfig extends AbstractConfig
{
    public function getReplStartupFile(): string
    {
        // Lives outside `src/` so the default `srcDirs = ['src']` walk
        // never picks up the bundled REPL bootstrap as a primary
        // `(ns user)` definition for downstream projects dogfooding Phel.
        return __DIR__ . '/../../../resources/repl/startup.phel';
    }
}
