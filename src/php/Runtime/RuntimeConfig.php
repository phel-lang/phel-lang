<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;

final class RuntimeConfig extends AbstractConfig
{
    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
