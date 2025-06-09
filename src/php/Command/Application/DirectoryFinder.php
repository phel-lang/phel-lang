<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\CodeDirectories;
use Phel\Command\Domain\Finder\DirectoryFinderInterface;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;

final readonly class DirectoryFinder implements DirectoryFinderInterface
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
        return $this->toAbsoluteDirectories($this->codeDirectories->getSourceDirs());
    }

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array
    {
        return $this->toAbsoluteDirectories($this->codeDirectories->getTestDirs());
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
        return $this->applicationRootDir . '/' . $this->codeDirectories->getOutputDir();
    }

    /**
     * @return list<string>
     */
    private function toAbsoluteDirectories(array $relativeDirectories): array
    {
        return array_map(function (string $dir): string {
            // PHAR path? return as-is
            if (str_starts_with($dir, 'phar://')) {
                return $dir;
            }

            // Absolute and resolvable path?
            $real = realpath($dir);
            if ($real !== false) {
                return $real;
            }

            // Relative to root dir (which may itself be a PHAR path)
            $joined = $this->applicationRootDir . '/' . $dir;

            // Try to resolve it too
            $resolved = realpath($joined);
            return $resolved !== false ? $resolved : $joined;
        }, $relativeDirectories);
    }
}
