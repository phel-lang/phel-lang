<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

/**
 * Stateless helper that yields every `.phel` file under a directory tree.
 * Returns an empty iterable if the directory cannot be opened.
 */
final class PhelFileIterator
{
    /**
     * @return iterable<string>
     */
    public static function iterate(string $directory): iterable
    {
        try {
            $dirIterator = new RecursiveDirectoryIterator($directory);
            $iterator = new RecursiveIteratorIterator($dirIterator);
            $regex = new RegexIterator($iterator, '/^.+\.phel$/i', RegexIterator::GET_MATCH);
        } catch (UnexpectedValueException) {
            return;
        }

        foreach ($regex as $match) {
            yield $match[0];
        }
    }
}
