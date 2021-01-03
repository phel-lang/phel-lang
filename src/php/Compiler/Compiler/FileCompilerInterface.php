<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

interface FileCompilerInterface
{
    public function compile(string $filename): string;
}
