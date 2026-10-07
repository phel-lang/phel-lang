<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Lint\Domain\Exception\LintSourceException;
use Phel\Shared\ScalarCoercion;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function is_array;
use function is_dir;
use function is_file;
use function realpath;

/**
 * Expands a mix of files and directories on the CLI into a deduplicated
 * flat list of `.phel` file paths. Directories are walked recursively.
 *
 * @internal
 */
final class FileCollector
{
    /**
     * @param list<string> $paths
     *
     * @throws LintSourceException when a listed directory cannot be walked
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

        return $this->inLoadOrder($files);
    }

    /**
     * Sorted, so the run reads the same on every filesystem, with the files
     * that join a namespace through `in-ns` last: a `load` pulls them in, and
     * their loader has to be read first, as at runtime.
     *
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function inLoadOrder(array $files): array
    {
        sort($files);
        $parts = array_values(array_filter($files, $this->joinsNamespace(...)));

        return [...array_values(array_diff($files, $parts)), ...$parts];
    }

    private function joinsNamespace(string $file): bool
    {
        $head = (string) @file_get_contents($file, length: 4096);

        return preg_match('/\A(?:\s|;[^\n]*)*\(in-ns\b/', $head) === 1;
    }

    /**
     * @throws LintSourceException when the directory cannot be walked
     *
     * @return iterable<string>
     */
    private function iteratePhelFiles(string $directory): iterable
    {
        // An unreadable directory yields no files, and a lint run over zero
        // files reports "no issues" and exits 0. Raising keeps this symmetric
        // with an unreadable *file*, which already raises LintSourceException.
        try {
            $dirIterator = new RecursiveDirectoryIterator($directory);
            $iterator = new RecursiveIteratorIterator($dirIterator);
            $regex = new RegexIterator($iterator, '/^.+\.phel$/i', RegexIterator::GET_MATCH);
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw LintSourceException::cannotWalkDirectory($directory, $unexpectedValueException);
        }

        foreach ($regex as $match) {
            if (is_array($match) && isset($match[0])) {
                yield ScalarCoercion::toString($match[0]);
            }
        }
    }
}
