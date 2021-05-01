<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\Config;
use Phel\PhelAbstractConfig;

final class CommandConfig extends PhelAbstractConfig
{
    /**
     * @return string[]
     */
    public function getDefaultTestDirectories(): array
    {
        return $this->get('extra')['phel']['tests'] ?? [];
    }

    public function getApplicationRootDir(): string
    {
        return Config::getApplicationRootDir();
    }
}
