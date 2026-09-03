<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use function realpath;
use function str_starts_with;

/**
 * Canonicalises a source path before it is used as a cache key or compared
 * against another path.
 *
 * A `phar://` path is returned untouched: `realpath()` does not resolve inside
 * a PHAR and would report the file as missing. Shared by the plain extractor
 * and its caching decorator so the two cannot key on different spellings of
 * the same file.
 *
 * @internal
 */
final class SourcePathResolver
{
    public static function resolve(string $path): ?string
    {
        if (str_starts_with($path, 'phar://')) {
            return $path;
        }

        $real = realpath($path);

        return $real !== false ? $real : null;
    }
}
