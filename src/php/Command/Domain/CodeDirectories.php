<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

final class CodeDirectories
{
    /** @var list<string> */
    private array $srcDirs;

    /** @var list<string> */
    private array $testDirs;

    private string $outputDirectory;

    public function __construct(array $srcDirs, array $testDirs, string $outputDirectory)
    {
        $this->srcDirs = $srcDirs;
        $this->testDirs = $testDirs;
        $this->outputDirectory = $outputDirectory;
    }

    public function getSourceDirectories(): array
    {
        return $this->srcDirs;
    }

    public function getTestDirectories(): array
    {
        return $this->testDirs;
    }

    public function getOutputDirectory(): string
    {
        return $this->outputDirectory;
    }
}
