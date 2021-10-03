<?php

declare(strict_types=1);

namespace Phel\Command\Finder;

interface VendorDirectoriesFinderInterface
{
    /**
     * @return list<string>
     */
    public function findPhelSourceDirectories(): array;
}
