<?php

declare(strict_types=1);

namespace Phel\Shared\Interop;

final readonly class Wrapper
{
    public function __construct(
        private string $relativeFilenamePath,
        private string $compiledPhp,
    ) {}

    public function compiledPhp(): string
    {
        return $this->compiledPhp;
    }

    public function relativeFilenamePath(): string
    {
        return $this->relativeFilenamePath;
    }
}
