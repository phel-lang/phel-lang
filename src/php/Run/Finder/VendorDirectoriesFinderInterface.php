<?php

declare(strict_types=1);

namespace Phel\Run\Finder;

interface VendorDirectoriesFinderInterface
{
    public function findPhelSourceDirectories(): array;
}
