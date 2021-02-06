<?php

declare(strict_types=1);

namespace Phel\Interop\ReadModel;

final class Wrapper
{
    private string $destinationDir;
    private string $relativeFilenamePath;
    private string $compiledPhp;

    public function __construct(string $destinationDir, string $relativeFilenamePath, string $compiledPhp)
    {
        $this->destinationDir = $destinationDir;
        $this->relativeFilenamePath = $relativeFilenamePath;
        $this->compiledPhp = $compiledPhp;
    }

    public function destinationDir(): string
    {
        return $this->destinationDir;
    }

    public function compiledPhp(): string
    {
        return $this->compiledPhp;
    }

    public function dir(): string
    {
        return dirname($this->absolutePath());
    }

    public function absolutePath(): string
    {
        return $this->destinationDir . '/' . $this->relativeFilenamePath;
    }
}
