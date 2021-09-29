<?php

declare(strict_types=1);

namespace Phel\Command\Shared\Exceptions\Extractor;

use Phel\Command\Shared\Exceptions\Extractor\ReadModel\SourceMapInformation;

final class SourceMapExtractor implements SourceMapExtractorInterface
{
    public function extractFromFile(string $filename): SourceMapInformation
    {
        $f = fopen($filename, 'r');
        $phpPrefix = fgets($f);
        $filenameComment = fgets($f);
        $sourceMapComment = fgets($f);

        return new SourceMapInformation($filenameComment, $sourceMapComment);
    }
}
