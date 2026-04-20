<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function is_dir;
use function is_file;
use function realpath;

/**
 * Expands a mix of files and directories on the CLI into a deduplicated
 * flat list of `.phel` file paths. Directories are walked recursively.
 */
final class FileCollector
{
    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public function collect(array $paths): array
    {
        $files = [];
        $seen = [];

        foreach ($paths as $path) {
            $real = realpath($path);
            if ($real === false) {
                continue;
            }

            if (is_file($real)) {
                if (!isset($seen[$real])) {
                    $files[] = $real;
                    $seen[$real] = true;
                }

                continue;
            }

            if (is_dir($real)) {
                foreach ($this->iteratePhelFiles($real) as $file) {
                    if (!isset($seen[$file])) {
                        $files[] = $file;
                        $seen[$file] = true;
                    }
                }
            }
        }

        return $files;
    }

    /**
     * @return iterable<string>
     */
    private function iteratePhelFiles(string $directory): iterable
    {
        try {
            $dirIterator = new RecursiveDirectoryIterator($directory);
            $iterator = new RecursiveIteratorIterator($dirIterator);
            $regex = new RegexIterator($iterator, '/^.+\.phel$/i', RegexIterator::GET_MATCH);
        } catch (UnexpectedValueException) {
            return [];
        }

        foreach ($regex as $match) {
            yield $match[0];
        }
    }
}
