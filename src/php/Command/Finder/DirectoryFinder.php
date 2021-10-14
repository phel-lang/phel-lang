<?php

declare(strict_types=1);

namespace Phel\Command\Finder;

use Phel\Command\Domain\CodeDirectories;

final class DirectoryFinder implements DirectoryFinderInterface
{
    private string $applicationRootDir;
    private CodeDirectories $codeDirectories;
    private VendorDirectoriesFinderInterface $vendorDirectoriesFinder;
    private string $outputDirectory;

    public function __construct(
        string $applicationRootDir,
        CodeDirectories $configDirectories,
        VendorDirectoriesFinderInterface $vendorDirectoriesFinder,
        string $outputDirectory
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->codeDirectories = $configDirectories;
        $this->vendorDirectoriesFinder = $vendorDirectoriesFinder;
        $this->outputDirectory = $outputDirectory;
    }

    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array
    {
        return $this->toAbsoluteDirectories($this->codeDirectories->getSourceDirectories());
    }

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array
    {
        return $this->toAbsoluteDirectories($this->codeDirectories->getTestDirectories());
    }

    /**
     * @return list<string>
     */
    public function getVendorSourceDirectories(): array
    {
        return $this->vendorDirectoriesFinder->findPhelSourceDirectories();
    }

    public function getOutputDirectory(): string
    {
        return $this->applicationRootDir . '/' . $this->outputDirectory;
    }

    /**
     * @return list<string>
     */
    private function toAbsoluteDirectories(array $relativeDirectories): array
    {
        return array_map(
            fn (string $dir): string => $this->applicationRootDir . '/' . $dir,
            $relativeDirectories
        );
    }
}
