<?php

declare(strict_types=1);

namespace Phel\Build\Compile;

class BuildOptions
{
    private bool $enableCache;
    private bool $enableSourceMap;

    public function __construct(bool $enableCache, bool $enableSourceMap)
    {
        $this->enableCache = $enableCache;
        $this->enableSourceMap = $enableSourceMap;
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
