<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\CodeDirectories;
use Phel\Command\Domain\Finder\DirectoryFinderInterface;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use Phel\Shared\PhelProjectDirectory;

/**
 * @internal
 */
final readonly class DirectoryFinder implements DirectoryFinderInterface
{
    public function __construct(
        private string $applicationRootDir,
        private CodeDirectories $codeDirectories,
        private VendorDirectoriesFinderInterface $vendorDirectoriesFinder,
    ) {}

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
    public function getProjectSourceDirectories(): array
    {
        return $this->toAbsoluteDirectories($this->codeDirectories->getProjectSourceDirs());
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

    /**
     * @return list<string>
     */
    public function getAllPhelDirectories(): array
    {
        return [
            ...$this->getSourceDirectories(),
            ...$this->getTestDirectories(),
            ...$this->getVendorSourceDirectories(),
        ];
    }

    public function getOutputDirectory(): string
    {
        return $this->applicationRootDir . '/' . $this->codeDirectories->getOutputDir();
    }

    /**
     * @param list<string> $relativeDirectories
     *
     * @return list<string>
     */
    private function toAbsoluteDirectories(array $relativeDirectories): array
    {
        $absolute = array_map(function (string $dir): string {
            // realpath() cannot resolve a phar:// stream path, so hand it back unchanged.
            if (str_starts_with($dir, 'phar://')) {
                return $dir;
            }

            $joined = PhelProjectDirectory::isAbsolutePath($dir)
                ? $dir
                : $this->applicationRootDir . '/' . $dir;
            $resolved = realpath($joined);
            return $resolved !== false ? $resolved : $joined;
        }, $relativeDirectories);

        // Dedupe identical entries so the same physical directory is not walked
        // twice (e.g. when the prepended phel core dir resolves to the same path
        // as a configured src dir, as happens inside a PHAR).
        return array_values(array_unique($absolute));
    }
}
