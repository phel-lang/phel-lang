<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Shared\ScalarCoercion;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function is_array;
use function str_starts_with;

/**
 * Stateless helper that yields every `.phel` file under a directory tree.
 * Returns an empty iterable if the directory cannot be opened.
 *
 * @internal
 */
final class PhelFileIterator
{
    /**
     * @param list<string> $excludedPrefixes absolute paths whose subtrees are skipped
     *
     * @return iterable<string>
     */
    public static function iterate(string $directory, array $excludedPrefixes = []): iterable
    {
        try {
            $dirIterator = new RecursiveDirectoryIterator($directory);
            $iterator = new RecursiveIteratorIterator($dirIterator);
            $regex = new RegexIterator($iterator, '/^.+\.phel$/i', RegexIterator::GET_MATCH);
        } catch (UnexpectedValueException) {
            return;
        }

        foreach ($regex as $match) {
            if (!is_array($match)) {
                continue;
            }

            if (!isset($match[0])) {
                continue;
            }

            $file = ScalarCoercion::toString($match[0]);
            if (self::isExcluded($file, $excludedPrefixes)) {
                continue;
            }

            yield $file;
        }
    }

    /**
     * @param list<string> $excludedPrefixes
     */
    private static function isExcluded(string $file, array $excludedPrefixes): bool
    {
        return array_any($excludedPrefixes, static fn(string $prefix): bool => str_starts_with($file, $prefix));
    }
}
