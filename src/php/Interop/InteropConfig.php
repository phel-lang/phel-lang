<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel\PhelAbstractConfig;

final class InteropConfig extends PhelAbstractConfig
{
    public function prefixNamespace(): string
    {
        return (string)$this->get('extra')['phel']['export']['namespace-prefix'];
    }

    public function getExportTargetDirectory(): string
    {
        return (string)($this->get('extra')['phel']['export']['target-directory'] ?? 'PhelGenerated');
    }

    /**
     * @return string[]
     */
    public function getExportDirectories(): array
    {
        return $this->get('extra')['phel']['export']['directories'] ?? [];
    }
}
