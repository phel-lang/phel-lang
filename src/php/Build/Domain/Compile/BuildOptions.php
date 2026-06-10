<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

final readonly class BuildOptions
{
    public function __construct(
        private bool $enableCache,
        private bool $enableSourceMap,
        private ?int $optimizationLevel = null,
    ) {}

    public function isCacheEnabled(): bool
    {
        return $this->enableCache;
    }

    public function isSourceMapEnabled(): bool
    {
        return $this->enableSourceMap;
    }

    /**
     * CLI override for the configured optimization level; `null` means
     * "use the level from `phel-config.php`".
     */
    public function getOptimizationLevel(): ?int
    {
        return $this->optimizationLevel;
    }
}
