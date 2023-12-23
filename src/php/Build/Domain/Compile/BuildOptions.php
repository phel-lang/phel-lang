<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

class BuildOptions
{
    public function __construct(
        private readonly bool $enableCache,
        private readonly bool $enableSourceMap,
    ) {
    }

    public function isCacheEnabled(): bool
    {
        return $this->enableCache;
    }

    public function isSourceMapEnabled(): bool
    {
        return $this->enableSourceMap;
    }
}
