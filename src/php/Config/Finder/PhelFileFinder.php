<?php

declare(strict_types=1);

namespace Phel\Config\Finder;

use AppendIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

class PhelFileFinder implements PhelFileFinderInterface
{
    private const PHEL_EXTENSION_REGEX = '/^.+\.phel$/i';

    /**
     * Finds a list of all phel files in the given directoies.
     *
     * @param list<string> The list of directories
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


        return new RegexIterator($appendIterator, self::PHEL_EXTENSION_REGEX, RecursiveRegexIterator::GET_MATCH);
    }
}
