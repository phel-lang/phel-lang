<?php

declare(strict_types=1);

namespace Phel\Exceptions\Extractor;

use Phel\Exceptions\Extractor\ReadModel\SourceMapInformation;

interface SourceMapExtractorInterface
{
    public function extractFromFile(string $filename): SourceMapInformation;
}
