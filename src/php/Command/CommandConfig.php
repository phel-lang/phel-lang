<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;

final class CommandConfig extends AbstractConfig
{
    public const TEST_DIRECTORIES = 'CommandConfig::TEST';

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array
    {
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $this->get(self::TEST_DIRECTORIES, [])
        );
    }

    public function getPhelReplHistory(): string
    {
        return $this->getApplicationRootDir() . '/.phel-repl-history';
    }

    public function getReplStartupPhel(): string
    {
        return __DIR__ . '/Repl/startup.phel';
    }

    private function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
