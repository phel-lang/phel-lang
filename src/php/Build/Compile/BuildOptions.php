<?php

declare(strict_types=1);

namespace Phel\Build\Compile;

class BuildOptions
{
    private bool $enableCache = true;
    private bool $enableSourceMap = true;

    public function __construct(bool $enableCache, bool $enableSourceMap)
    {
        $this->enableCache = $enableCache;
        $this->enableSourceMap = $enableSourceMap;
    }

    public function getEnableCache(): bool
    {
        return $this->enableCache;
    }

    public function getEnableSourceMap(): bool
    {
        return $this->enableSourceMap;
    }
}
