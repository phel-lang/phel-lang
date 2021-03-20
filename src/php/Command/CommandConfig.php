<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\AbstractConfig;
use Gacela\Config;

final class CommandConfig extends AbstractConfig
{
    public const DEFAULT_TEST_DIRECTORIES = 'DEFAULT_TEST_DIRECTORIES';

    public function getDefaultTestDirectories(): array
    {
        return $this->get(self::DEFAULT_TEST_DIRECTORIES, []);
    }

    public function getApplicationRootDir(): string
    {
        return Config::getApplicationRootDir();
    }
}
