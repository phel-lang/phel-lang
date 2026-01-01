<?php

declare(strict_types=1);

namespace Phel\Config;

enum ProjectLayout: string
{
    case Conventional = 'conventional';
    case Flat = 'flat';

    public function getSrcDir(): string
    {
        return match ($this) {
            self::Conventional => 'src/phel',
            self::Flat => 'src',
        };
    }

    public function getTestDir(): string
    {
        return match ($this) {
            self::Conventional => 'tests/phel',
            self::Flat => 'tests',
        };
    }

    /**
     * @return list<string>
     */
    public function getFormatDirs(): array
    {
        return [$this->getSrcDir(), $this->getTestDir()];
    }

    /**
     * @return list<string>
     */
    public function getExportFromDirs(): array
    {
        return [$this->getSrcDir()];
    }
}
