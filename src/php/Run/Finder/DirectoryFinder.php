<?php

declare(strict_types=1);

namespace Phel\Run\Finder;

final class DirectoryFinder implements DirectoryFinderInterface
{
    private string $applicationRootDir;
    private array $srcDirs;
    private array $testDirs;
    private VendorDirectoriesFinderInterface $vendorDirectoriesFinder;

    public function __construct(
        string $applicationRootDir,
        array $srcDirs,
        array $testDirs,
        VendorDirectoriesFinderInterface $vendorDirectoriesFinder
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->srcDirs = $srcDirs;
        $this->testDirs = $testDirs;
        $this->vendorDirectoriesFinder = $vendorDirectoriesFinder;
    }

    /**
     * @return list<string>
     */
    public function getAbsoluteSourceDirectories(): array
    {
        return $this->toAbsoluteDirectories($this->srcDirs);
    }

    /**
     * @return list<string>
     */
    public function getAbsoluteTestDirectories(): array
    {
        return $this->toAbsoluteDirectories($this->testDirs);
    }

    /**
     * @return list<string>
     */
    public function getAbsoluteVendorSourceDirectories(): array
    {
        return $this->vendorDirectoriesFinder->findPhelSourceDirectories();
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
