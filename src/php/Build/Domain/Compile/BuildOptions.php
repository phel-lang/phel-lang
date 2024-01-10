<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

final readonly class BuildOptions
{
    public function __construct(
        private bool $enableCache,
        private bool $enableSourceMap,
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
