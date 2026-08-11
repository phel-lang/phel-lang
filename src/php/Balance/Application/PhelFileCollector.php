<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\Exception\BalanceSourceException;
use Phel\Balance\Domain\FileCollectorInterface;
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
 * Expands a mix of files and directories into a deduplicated flat list of
 * `.phel` paths, walking directories recursively.
 *
 * @internal
 */
final class PhelFileCollector implements FileCollectorInterface
{
    /**
     * @param list<string> $paths
     *
     * @throws BalanceSourceException when a listed directory cannot be walked
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

            if (!is_dir($real)) {
                continue;
            }

            foreach ($this->iteratePhelFiles($real) as $file) {
                if (!isset($seen[$file])) {
                    $files[] = $file;
                    $seen[$file] = true;
                }
            }
        }

        return $files;
    }

    /**
     * @throws BalanceSourceException
     *
     * @return iterable<string>
     */
    private function iteratePhelFiles(string $directory): iterable
    {
        // Silently yielding nothing would report "all balanced" and exit 0 over
        // a directory nobody could read, which is the one answer a repair tool
        // must not give.
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            $regex = new RegexIterator($iterator, '/^.+\.phel$/i', RegexIterator::GET_MATCH);
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw BalanceSourceException::cannotWalkDirectory($directory, $unexpectedValueException);
        }

        foreach ($regex as $match) {
            if (is_array($match) && isset($match[0])) {
                yield ScalarCoercion::toString($match[0]);
            }
        }
    }
}
