<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;

final class RunConfig extends AbstractConfig
{
    public function getPhelReplHistory(): string
    {
        return $this->getApplicationRootDir() . '.phel-repl-history';
    }

    public function getReplStartupFile(): string
    {
        return __DIR__ . '/Domain/Repl/startup.phel';
    }

    private function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
