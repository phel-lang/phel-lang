<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Finder;

interface DirectoryFinderInterface
{
    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array;

    /**
     * Source directories configured by the user — excludes phel's own
     * bundled stdlib directory that is prepended for runtime namespace
     * resolution.
     *
     * @return list<string>
     */
    public function getProjectSourceDirectories(): array;

    /**
     * @return list<string>
     */
    public function getTestDirectories(): array;

    /**
     * @return list<string>
     */
    public function getVendorSourceDirectories(): array;

    /**
     * @return list<string>
     */
    public function getAllPhelDirectories(): array;

    public function getOutputDirectory(): string;
}
