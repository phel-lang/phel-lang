<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\Config;
use Phel\AbstractPhelConfig;

final class CommandConfig extends AbstractPhelConfig
{
    public const TESTS = 'tests';

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array
    {
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $this->get(self::TESTS, [])
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
