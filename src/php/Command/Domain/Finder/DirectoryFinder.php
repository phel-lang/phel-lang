<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Finder;

use Phel\Command\Domain\CodeDirectories;

final class DirectoryFinder implements DirectoryFinderInterface
{
    public function __construct(
        private string $applicationRootDir,
        private CodeDirectories $codeDirectories,
        private VendorDirectoriesFinderInterface $vendorDirectoriesFinder,
    ) {
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
        return $this->applicationRootDir . '/' . $this->codeDirectories->getOutputDirectory();
    }

    /**
     * @return list<string>
     */
    private function toAbsoluteDirectories(array $relativeDirectories): array
    {
        return array_map(
            fn (string $dir): string => $this->applicationRootDir . '/' . $dir,
            $relativeDirectories,
        );
    }
}
