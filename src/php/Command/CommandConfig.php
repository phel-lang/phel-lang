<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\AbstractPhelConfig;

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
}
