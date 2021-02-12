<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\AbstractPhelConfig;
use RuntimeException;

final class CommandConfig extends AbstractPhelConfig implements CommandConfigInterface
{
    public function getDefaultTestDirectories(): array
    {
        return $this->get('tests') ?? [];
    }

    public function getExportDirectories(): array
    {
        return $this->get('export')['directories'] ?? [];
    }

    public function getExportTargetDirectory(): string
    {
        $targetDirectory = $this->get('export')['target-directory'] ?? '';

        if (empty($targetDirectory)) {
            throw new RuntimeException('Missing composer option: export.target-directory');
        }

        return $targetDirectory;
    }
}
