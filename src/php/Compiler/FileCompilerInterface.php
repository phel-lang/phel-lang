<?php

declare(strict_types=1);

namespace Phel\Compiler;

interface FileCompilerInterface
{
    public function compile(string $filename): string;
}
