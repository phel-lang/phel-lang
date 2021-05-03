<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\AbstractPhelConfig;

final class InteropConfig extends AbstractPhelConfig
{
    public function prefixNamespace(): string
    {
        return (string)$this->get('export')['namespace-prefix'];
    }

    public function getExportTargetDirectory(): string
    {
        return (string)($this->get('export')['target-directory'] ?? 'PhelGenerated');
    }

    /**
     * @return string[]
     */
    public function getExportDirectories(): array
    {
        return $this->get('export')['directories'] ?? [];
    }
}
