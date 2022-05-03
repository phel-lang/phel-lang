<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Finder;

interface VendorDirectoriesFinderInterface
{
    /**
     * @return list<string>
     */
    public function findPhelSourceDirectories(): array;
}
