<?php

declare(strict_types=1);

namespace Phel\Config;

use Gacela\Framework\AbstractFacade;
use Iterator;

/**
 * @method ConfigFactory getFactory()
 */
final class ConfigFacade extends AbstractFacade implements ConfigFacadeInterface
{
    /**
     * Returns a list of all source directories in the project.
     * All path are absolute.
     *
     * @return list<string>
     */
    public function getSourceDirectories(): array
    {
        return $this->getFactory()
            ->createDirectoryFinder()
            ->getAbsoluteSourceDirectories();
    }

    /**
     * Returns an iterator of all phel files in the project's source directories.
     */
    public function getSourceFiles(): Iterator
    {
        return $this->getFactory()
            ->getPhelFileFinder()
            ->findPhelFiles($this->getSourceDirectories());
    }

    /**
     * Returns a list of all test directories in the project.
     * All path are absolute.
     *
     * @return list<string>
     */
    public function getTestDirectories(): array
    {
        return $this->getFactory()
            ->createDirectoryFinder()
            ->getAbsoluteTestDirectories();
    }

    /**
     * Returns an iterator of all phel files in the project's test directories.
     */
    public function getTestFiles(): Iterator
    {
        return $this->getFactory()
            ->getPhelFileFinder()
            ->findPhelFiles($this->getTestDirectories());
    }

    /**
     * Return the path of the vendor directory.
     * The path is absolute.
     *
     * @return string
     */
    public function getVendorDirectory(): string
    {
        return $this->getFactory()
            ->createDirectoryFinder()
            ->getAbsoluteVendorDir();
    }

    /**
     * Returns a list of all source directories is the vendor folder.
     * All path are absolute.
     *
     * @return list<string>
     */
    public function getVendorSourceDirectories(): array
    {
        return $this->getFactory()
            ->getVendorDirectoryFinder()
            ->findPhelSourceDirectories();
    }

    /**
     * Returns an iterator of all phel files in the project's vendor source directories.
     */
    public function getVendorSourceFiles(): Iterator
    {
        return $this->getFactory()
            ->getPhelFileFinder()
            ->findPhelFiles($this->getVendorSourceDirectories());
    }

    /**
     * Returns a iterator of all phel files in the project's source directories and vendor source directories.
     */
    public function getAllSourceFiles(): Iterator
    {
        return $this->getFactory()
            ->getPhelFileFinder()
            ->findPhelFiles([
                ...$this->getSourceDirectories(),
                ...$this->getVendorSourceDirectories(),
            ]);
    }

    /**
     * Returns a iterator of all phel files in the project's source directories, test directories and vendor source directories.
     */
    public function getAllFiles(): Iterator
    {
        return $this->getFactory()->getPhelFileFinder()
            ->findPhelFiles([
                ...$this->getSourceDirectories(),
                ...$this->getTestDirectories(),
                ...$this->getVendorSourceDirectories(),
            ]);
    }
}
