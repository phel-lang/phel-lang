<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use function basename;
use function dirname;
use function realpath;
use function rtrim;
use function str_contains;

/**
 * @internal
 */
final class SourceFilePath
{
    private function __construct() {}

    public static function normalize(string $sourcePath): string
    {
        if (str_contains($sourcePath, '://')) {
            return $sourcePath;
        }

        return rtrim(self::directoryPath(dirname($sourcePath)), '/\\') . DIRECTORY_SEPARATOR . basename($sourcePath);
    }

    private static function directoryPath(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved !== false) {
            return $resolved;
        }

        $parent = dirname($path);
        if ($parent === $path) {
            return $path;
        }

        $parent = self::directoryPath($parent);
        return match (basename($path)) {
            '.' => $parent,
            '..' => dirname($parent),
            default => rtrim($parent, '/\\') . DIRECTORY_SEPARATOR . basename($path),
        };
    }
}
