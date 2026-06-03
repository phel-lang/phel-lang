<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Domain\Exceptions\Extractor\SourceMapExtractorInterface;

use function fclose;
use function fgets;
use function fopen;

/**
 * Reads the first two lines of a generated PHP file, which Phel emits as source
 * map metadata comments (a `// ` filename comment followed by a `// ` source map
 * comment). Both lines are returned verbatim for the caller to parse.
 */
final class SourceMapExtractor implements SourceMapExtractorInterface
{
    /**
     * Returns the raw first two lines of the file. Empty strings indicate that no
     * source map is available, i.e. the file is missing, unreadable, or shorter
     * than two lines; an empty string is therefore indistinguishable from a truly
     * blank line, so callers must treat empty as "no source map".
     */
    public function extractFromFile(string $filename): SourceMapInformation
    {
        if (!file_exists($filename)) {
            return new SourceMapInformation('', '');
        }

        $handle = fopen($filename, 'rb');

        if ($handle === false) {
            return new SourceMapInformation('', '');
        }

        $filenameComment = fgets($handle);
        $sourceMapComment = fgets($handle);

        fclose($handle);

        return new SourceMapInformation(
            $filenameComment === false ? '' : $filenameComment,
            $sourceMapComment === false ? '' : $sourceMapComment,
        );
    }
}
