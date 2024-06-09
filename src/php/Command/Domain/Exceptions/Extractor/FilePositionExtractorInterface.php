<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions\Extractor;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;

interface FilePositionExtractorInterface
{
    public function getOriginal(string $filename, int $line): FilePosition;
}
