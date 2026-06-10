<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\CompileOptions;

final class RunConfig extends AbstractConfig
{
    public function getReplStartupFile(): string
    {
        // Lives outside `src/` so the default `srcDirs = ['src']` walk
        // never picks up the bundled REPL bootstrap as a primary
        // `(ns user)` definition for downstream projects dogfooding Phel.
        return __DIR__ . '/../../../resources/repl/startup.phel';
    }

    public function getOptimizationLevel(): int
    {
        return max(0, (int) $this->get(PhelConfig::OPTIMIZATION_LEVEL, CompileOptions::DEFAULT_OPTIMIZATION_LEVEL));
    }
}
