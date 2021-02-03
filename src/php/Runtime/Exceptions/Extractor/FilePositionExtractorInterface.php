<?php

declare(strict_types=1);

namespace Phel\Runtime\Exceptions\Extractor;

use Phel\Runtime\Exceptions\Extractor\ReadModel\FilePosition;

interface FilePositionExtractorInterface
{
    public function getOriginal(string $filename, int $line): FilePosition;
}
