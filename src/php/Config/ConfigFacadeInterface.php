<?php

declare(strict_types=1);

namespace Phel\Config;

use Iterator;

interface ConfigFacadeInterface
{
    /**
     * Returns a list of all source directories in the project.
     *
     * All path are absolute
     *
     * @return list<string>
     */
    public function getSourceDirectories(): array;

    /**
     * Returns an iterator of all phel files in the project's source directories.
     */
    public function getSourceFiles(): Iterator;

    /**
     * Returns a list of all test directories in the project.
     *
     * All path are absolute
     *
     * @return list<string>
     */
    public function getTestDirectories(): array;

    /**
     * Returns an iterator of all phel files in the project's test directories.
     */
    public function getTestFiles(): Iterator;

    /**
     * Return the path of the vendor directory.
     *
     * The path is absolute
     *
     * @return string
     */
    public function getVendorDirectory(): string;

    /**
     * Returns a list of all source directories is the vendor folder.
     *
     * All path are absolute
     *
     * @return list<string>
     */
    public function getVendorSourceDirectories(): array;

    /**
     * Returns an iterator of all phel files in the project's source directories and vendor source directories.
     */
    public function getAllSourceFiles(): Iterator;

    /**
     * Returns an iterator of all phel files in the project's source directories, test directories and vendor source directories.
     */
    public function getAllFiles(): Iterator;
}
