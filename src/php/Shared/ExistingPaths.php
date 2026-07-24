<?php

declare(strict_types=1);

namespace Phel\Shared;

use function is_dir;
use function is_file;

/**
 * Drops paths that name neither a readable file nor a directory, so CLI
 * commands can accept user-supplied paths and report an empty selection
 * instead of failing per entry.
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
            if (is_file($path) || is_dir($path)) {
                $filtered[] = $path;
            }
        }

        return $filtered;
    }
}
