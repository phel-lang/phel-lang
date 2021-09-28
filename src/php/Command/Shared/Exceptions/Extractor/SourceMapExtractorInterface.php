<?php

declare(strict_types=1);

namespace Phel\Command\Shared\Exceptions\Extractor;

use Phel\Runtime\Exceptions\Extractor\ReadModel\SourceMapInformation;

interface SourceMapExtractorInterface
{
    public function extractFromFile(string $filename): SourceMapInformation;
}
