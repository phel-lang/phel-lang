<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Generator;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Domain\Exceptions\Extractor\SourceMapExtractorInterface;
use Phel\Shared\SourceMap\BuiltFilePreamble;
use Phel\Shared\SourceMap\InlineSourceMapComments;
use Phel\Shared\SourceMap\SourceMapSiblings;
use Phel\Shared\SourceMap\SupersededSourceMaps;

use function fclose;
use function fgets;
use function fopen;
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
 *    are scanned for the comment pair. When this process loaded a compiled
 *    file that was overwritten since, the header it loaded wins over the file
 *    (see SupersededSourceMaps).
 * 2. Sibling files (built output): `phel build` writes the mappings next to
 *    the compiled file as `<file>.map` and a copy of the source as
 *    `<file>.phel` (see FileCompiler), with no inline comments.
 *
 * @internal
 */
final class SourceMapExtractor implements SourceMapExtractorInterface
{
    public function extractFromFile(string $filename): SourceMapInformation
    {
        return $this->extractInline($filename)
            ?? $this->extractFromSiblingFiles($filename);
    }

    private function extractInline(string $filename): ?SourceMapInformation
    {
        $sourceFilename = '';
        $lineNumber = 0;

        foreach ($this->headerLines($filename) as $line) {
            ++$lineNumber;

            // The mappings check must come first: FILENAME_PREFIX is a
            // prefix of MAPPINGS_PREFIX, so reversing the order would
            // capture the mappings line as a filename.
            if (str_starts_with($line, InlineSourceMapComments::MAPPINGS_PREFIX)) {
                if ($sourceFilename === '') {
                    return null;
                }

                return new SourceMapInformation(
                    $sourceFilename,
                    trim(substr($line, strlen(InlineSourceMapComments::MAPPINGS_PREFIX))),
                    $lineNumber + 1,
                );
            }

            if (str_starts_with($line, InlineSourceMapComments::FILENAME_PREFIX)) {
                $sourceFilename = trim(substr($line, strlen(InlineSourceMapComments::FILENAME_PREFIX)));
            }
        }

        return null;
    }

    /**
     * @return Generator<int, string>
     */
    private function headerLines(string $filename): Generator
    {
        $superseded = SupersededSourceMaps::headerOf($filename);
        if ($superseded !== null) {
            yield from $superseded;

            return;
        }

        if (!is_file($filename)) {
            return;
        }

        $handle = fopen($filename, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            for ($i = 0; $i < SupersededSourceMaps::HEADER_LINES; ++$i) {
                $line = fgets($handle);
                if ($line === false) {
                    return;
                }

                yield $line;
            }
        } finally {
            fclose($handle);
        }
    }

    private function extractFromSiblingFiles(string $filename): SourceMapInformation
    {
        $mapFile = SourceMapSiblings::mapFile($filename);
        $sourceFile = SourceMapSiblings::sourceFile($filename);

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
            BuiltFilePreamble::codeStartLine(),
        );
    }
}
