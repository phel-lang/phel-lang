<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

final readonly class CodeDirectories
{
    /**
     * @param list<string> $srcDirs
     * @param list<string> $testDirs
     */
    public function __construct(
        private array $srcDirs,
        private array $testDirs,
        private string $outputDir,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getSourceDirs(): array
    {
        return $this->srcDirs;
    }

    /**
     * @return list<string>
     */
    public function getTestDirs(): array
    {
        return $this->testDirs;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }
}
