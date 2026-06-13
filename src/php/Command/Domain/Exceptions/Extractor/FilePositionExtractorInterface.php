<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions\Extractor;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;

interface FilePositionExtractorInterface
{
    public function getOriginal(string $filename, int $line): FilePosition;

    /**
     * Maps every mapped generated line of a compiled PHP file back to its
     * Phel source. Returns the originating `.phel` filename and a
     * `[phpLine => phelLine]` map; empty filename when the file carries no
     * source map.
     *
     * @return array{filename: string, lines: array<int, int>}
     */
    public function getFileLineMap(string $filename): array;
}
