<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

final class CodeDirectories
{
    /** @var list<string> */
    private array $srcDirs;

    /** @var list<string> */
    private array $testDirs;

    public function __construct(array $srcDirs, array $testDirs)
    {
        $this->srcDirs = $srcDirs;
        $this->testDirs = $testDirs;
    }

    public function getSourceDirectories(): array
    {
        return $this->srcDirs;
    }

    public function getTestDirectories(): array
    {
        return $this->testDirs;
    }
}
