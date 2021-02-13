<?php

declare(strict_types=1);

namespace Phel\Interop\ReadModel;

final class Wrapper
{
    private string $relativeFilenamePath;
    private string $compiledPhp;

    public function __construct(string $relativeFilenamePath, string $compiledPhp)
    {
        $this->relativeFilenamePath = $relativeFilenamePath;
        $this->compiledPhp = $compiledPhp;
    }


    public function compiledPhp(): string
    {
        return $this->compiledPhp;
    }

    public function relativeFilenamePath(): string
    {
        return $this->relativeFilenamePath;
    }

    public function dir(): string
    {
        return dirname($this->relativeFilenamePath);
    }
}
