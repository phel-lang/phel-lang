<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

class BuildOptions
{
    public function __construct(
        private bool $enableCache,
        private bool $enableSourceMap,
        private ?string $mainNamespace,
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

    public function getMainNamespace(): ?string
    {
        return $this->mainNamespace;
    }
}
