<?php

declare(strict_types=1);

namespace Phel\Config\Finder;

interface VendorDirectoriesFinderInterface
{
    public function findPhelSourceDirectories(): array;
}
