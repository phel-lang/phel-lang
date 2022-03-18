<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractConfig;

final class RunConfig extends AbstractConfig
{
    public function getPhelReplHistory(): string
    {
        return $this->getAppRootDir() . '.phel-repl-history';
    }

    public function getReplStartupFile(): string
    {
        return __DIR__ . '/Domain/Repl/startup.phel';
    }
}
