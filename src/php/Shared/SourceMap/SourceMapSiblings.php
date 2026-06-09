<?php

declare(strict_types=1);

namespace Phel\Shared\SourceMap;

use function str_ends_with;
use function strlen;
use function substr;

/**
 * Naming convention for the artifacts `phel build` writes next to each
 * compiled PHP file: the VLQ source map (`<file>.php.map`) and a copy of the
 * Phel source (`<file>.phel`). The build writes them (FileCompiler) and the
 * error printer reads them back (SourceMapExtractor), so the convention lives
 * here in Shared.
 */
final class SourceMapSiblings
{
    private const string PHP_SUFFIX = '.php';

    private const string PHEL_SUFFIX = '.phel';

    private const string MAP_SUFFIX = '.map';

    private function __construct() {}

    public static function mapFile(string $compiledFile): string
    {
        return $compiledFile . self::MAP_SUFFIX;
    }

    public static function sourceFile(string $compiledFile): string
    {
        if (str_ends_with($compiledFile, self::PHP_SUFFIX)) {
            $compiledFile = substr($compiledFile, 0, -strlen(self::PHP_SUFFIX));
        }

        return $compiledFile . self::PHEL_SUFFIX;
    }
}
