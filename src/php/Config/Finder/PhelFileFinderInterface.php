<?php

declare(strict_types=1);

namespace Phel\Config\Finder;

use Iterator;

interface PhelFileFinderInterface
{
    /**
     * Finds a list of all phel files in the given directoies.
     *
     * @param list<string> The list of directories
     *
     * @return Iterator<string> List of all files with the `.phel` extension.
     */
    public function findPhelFiles(array $directories): Iterator;
}
