<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Domain\Exceptions\Extractor\SourceMapExtractorInterface;

use function fclose;
use function fgets;
use function fopen;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Extracts source-map metadata from a compiled PHP file so runtime stack
 * traces can be mapped back to the original Phel source.
 *
 * Two layouts are supported:
 *
 * 1. Inline (eval temp files): the emitter prepends a `// <source>` filename
 *    comment followed by a `// ;;<mappings>` comment. These may sit below a
 *    `<?php` opener and optional declare statements, so the first few lines
 *    are scanned for the comment pair.
 * 2. Sibling files (built output): `phel build` writes the mappings next to
 *    the compiled file as `<file>.map` and a copy of the source as
 *    `<file>.phel` (see FileCompiler), with no inline comments.
 */
final class SourceMapExtractor implements SourceMapExtractorInterface
{
    /**
     * Inline metadata sits within the first lines of an eval temp file:
     * `<?php`, an optional `declare(ticks=1);`, then the two comments.
     */
    private const int MAX_HEADER_LINES = 4;

    private const string FILENAME_COMMENT_PREFIX = '// ';

    private const string MAPPINGS_COMMENT_PREFIX = '// ;;';

    /**
     * Built files start with a single `<?php declare(strict_types=1);` line
     * (see FileCompiler), so the generated code begins on line 2.
     */
    private const int BUILT_FILE_CODE_START_LINE = 2;

    public function extractFromFile(string $filename): SourceMapInformation
    {
        return $this->extractInline($filename)
            ?? $this->extractFromSiblingFiles($filename);
    }

    private function extractInline(string $filename): ?SourceMapInformation
    {
        if (!is_file($filename)) {
            return null;
        }

        $handle = fopen($filename, 'rb');

        if ($handle === false) {
            return null;
        }

        $sourceFilename = '';

        try {
            for ($lineNumber = 1; $lineNumber <= self::MAX_HEADER_LINES; ++$lineNumber) {
                $line = fgets($handle);

                if ($line === false) {
                    return null;
                }

                if (str_starts_with($line, self::MAPPINGS_COMMENT_PREFIX)) {
                    if ($sourceFilename === '') {
                        return null;
                    }

                    return new SourceMapInformation(
                        $sourceFilename,
                        trim(substr($line, strlen(self::MAPPINGS_COMMENT_PREFIX))),
                        $lineNumber + 1,
                    );
                }

                if (str_starts_with($line, self::FILENAME_COMMENT_PREFIX)) {
                    $sourceFilename = trim(substr($line, strlen(self::FILENAME_COMMENT_PREFIX)));
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private function extractFromSiblingFiles(string $filename): SourceMapInformation
    {
        $mapFile = sprintf('%s.map', $filename);
        $sourceFile = str_replace('.php', '.phel', $filename);

        if (!is_file($mapFile) || !is_file($sourceFile)) {
            return SourceMapInformation::none();
        }

        $mappings = file_get_contents($mapFile);

        if ($mappings === false || trim($mappings) === '') {
            return SourceMapInformation::none();
        }

        return new SourceMapInformation(
            $sourceFile,
            trim($mappings),
            self::BUILT_FILE_CODE_START_LINE,
        );
    }
}
