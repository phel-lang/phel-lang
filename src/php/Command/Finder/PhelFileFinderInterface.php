<?php

declare(strict_types=1);

namespace Phel\Command\Finder;

use Iterator;

interface PhelFileFinderInterface
{
    /**
     * Finds a list of all phel files in the given directories.
     *
     * @param list<string> $directories The list of directories
     *
     * @return Iterator<string> List of all files with the `.phel` extension.
     */
    public function findPhelFiles(array $directories): Iterator;
}
