<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Domain\Exceptions\Extractor\SourceMapExtractorInterface;

final class SourceMapExtractor implements SourceMapExtractorInterface
{
    public function extractFromFile(string $filename): SourceMapInformation
    {
        $f = fopen($filename, 'rb');
        $filenameComment = fgets($f);
        $sourceMapComment = fgets($f) ?: '';

        return new SourceMapInformation($filenameComment, $sourceMapComment);
    }
}
