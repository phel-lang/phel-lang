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
        return array_map(
            static fn (string $dir): string => Config::getInstance()->getApplicationRootDir() . '/' . $dir,
            $this->get('tests', [])
        );
    }

    public function getPhelReplHistory(): string
    {
        return Config::getInstance()->getApplicationRootDir() . '/.phel-repl-history';
    }

    public function getReplStartupPhel(): string
    {
        return __DIR__ . '/Repl/startup.phel';
    }
}
