<?php

declare(strict_types=1);

namespace Phel\Config\Finder;

final class DirectoryFinder
{
    private string $applicationRootDir;

    private array $srcDirs;
    private array $testDirs;
    private string $vendorDir;

    public function __construct(
        string $applicationRootDir,
        array $srcDirs,
        array $testDirs,
        string $vendorDir
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->srcDirs = $srcDirs;
        $this->testDirs = $testDirs;
        $this->vendorDir = $vendorDir;
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

    public function getAbsoluteVendorDir(): string
    {
        return $this->applicationRootDir . '/' . $this->vendorDir;
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
