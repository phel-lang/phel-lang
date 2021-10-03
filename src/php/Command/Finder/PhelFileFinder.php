<?php

declare(strict_types=1);

namespace Phel\Command\Finder;

use AppendIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

final class PhelFileFinder implements PhelFileFinderInterface
{
    private const PHEL_EXTENSION_REGEX = '/^.+\.phel$/i';

    /**
     * Finds a list of all phel files in the given directories.
     *
     * @param list<string> $directories The list of directories
     *
     * @return Iterator<string> List of all files with the `.phel` extension.
     */
    public function findPhelFiles(array $directories): Iterator
    {
        $appendIterator = new AppendIterator();
        foreach ($directories as $directory) {
            $appendIterator->append(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory))
            );
        }

        return new RegexIterator($appendIterator, self::PHEL_EXTENSION_REGEX, RegexIterator::GET_MATCH);
    }
}
