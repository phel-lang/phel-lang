<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\AbstractConfig;
use Gacela\Config;

final class RuntimeConfig extends AbstractConfig
{
    public function getApplicationRootDir(): string
    {
        return Config::getApplicationRootDir();
    }
}
