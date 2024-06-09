<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions\Extractor;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;

interface SourceMapExtractorInterface
{
    public function extractFromFile(string $filename): SourceMapInformation;
}
