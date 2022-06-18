<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

final class CodeDirectories
{
    /**
     * @param list<string> $srcDirs
     * @param list<string> $testDirs
     */
    public function __construct(
        private array $srcDirs,
        private array $testDirs,
        private string $outputDirectory,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array
    {
        return $this->srcDirs;
    }

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array
    {
        return $this->testDirs;
    }

    public function getOutputDirectory(): string
    {
        return $this->outputDirectory;
    }
}
