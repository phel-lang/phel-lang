<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\Config;
use Phel\AbstractPhelConfig;

final class CommandConfig extends AbstractPhelConfig
{
    /**
     * @return string[]
     */
    public function getTestDirectories(): array
    {
        return $this->get('tests', []);
    }

    public function getApplicationRootDir(): string
    {
        return Config::getApplicationRootDir();
    }
}
