<?php

declare(strict_types=1);

namespace Phel\Command\Finder;

interface DirectoryFinderInterface
{
    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array;

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array;

    /**
     * @return list<string>
     */
    public function getVendorSourceDirectories(): array;

    public function getOutputDirectory(): string;
}
