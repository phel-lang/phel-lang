<?php

declare(strict_types=1);

namespace Phel\Run\Finder;

interface VendorDirectoriesFinderInterface
{
    /**
     * @return list<string>
     */
    public function findPhelSourceDirectories(): array;
}
