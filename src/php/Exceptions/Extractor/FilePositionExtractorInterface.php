<?php

declare(strict_types=1);

namespace Phel\Exceptions\Extractor;

use Phel\Exceptions\Extractor\ReadModel\FilePosition;

interface FilePositionExtractorInterface
{
    public function getOriginal(string $filename, int $line): FilePosition;
}
