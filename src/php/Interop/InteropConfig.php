<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\Config;
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
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $this->get('export')['directories'] ?? []
        );
    }

    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
