<?php

declare(strict_types=1);

namespace Phel\Interop\ReadModel;

final class ExportConfig
{
    private string $targetDir;
    private string $prefixNamespace;

    public function __construct(string $targetDir, string $prefixNamespace)
    {
        $this->targetDir = $targetDir;
        $this->prefixNamespace = $prefixNamespace;
    }

    public function targetDir(): string
    {
        return $this->targetDir;
    }

    public function prefixNamespace(): string
    {
        return $this->prefixNamespace;
    }
}
