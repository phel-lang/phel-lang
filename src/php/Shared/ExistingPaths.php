<?php

declare(strict_types=1);

namespace Phel\Shared;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function is_dir;
use function is_file;
use function sprintf;

/**
 * Checks the paths a user typed on the command line. A path that names
 * neither a file nor a directory is an invocation error (exit 2), never a
 * silently smaller selection.
 */
final class ExistingPaths
{
    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public static function filter(array $paths): array
    {
        $filtered = [];
        foreach ($paths as $path) {
            if (self::exists($path)) {
                $filtered[] = $path;
            }
        }

        return $filtered;
    }

    /**
     * Writes one `Path not found` line to stderr per missing path.
     *
     * @internal
     *
     * @param list<string> $paths
     *
     * @return bool true when every path exists
     */
    public static function reportMissing(array $paths, OutputInterface $output): bool
    {
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $allExist = true;
        foreach ($paths as $path) {
            if (!self::exists($path)) {
                $stderr->writeln(sprintf('<error>Path not found: %s</error>', $path));
                $allExist = false;
            }
        }

        return $allExist;
    }

    private static function exists(string $path): bool
    {
        return is_file($path) || is_dir($path);
    }
}
