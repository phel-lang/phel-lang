<?php

declare(strict_types=1);

namespace Phel\Interop\ReadModel;

final class Wrapper
{
    private string $destinyPath;
    private string $compiledPhp;

    public function __construct(string $destinyPath, string $compiledPhp)
    {
        $this->destinyPath = $destinyPath;
        $this->compiledPhp = $compiledPhp;
    }

    public function destinyPath(): string
    {
        return $this->destinyPath;
    }

    public function compiledPhp(): string
    {
        return $this->compiledPhp;
    }
}
