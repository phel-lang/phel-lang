<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;

final class RunConfig extends AbstractConfig
{
    public const TESTS = 'tests';

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array
    {
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $this->get('tests', [])
        );
    }

    public function getPhelReplHistory(): string
    {
        return $this->getApplicationRootDir() . '/.phel-repl-history';
    }

    public function getReplStartupFile(): string
    {
        return __DIR__ . '/Repl/startup.phel';
    }

    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
