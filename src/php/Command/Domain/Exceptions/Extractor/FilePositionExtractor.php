<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions\Extractor;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapConsumer;

final readonly class FilePositionExtractor implements FilePositionExtractorInterface
{
    public function __construct(
        private SourceMapExtractorInterface $sourceMapExtractor,
    ) {
    }

    public function getOriginal(string $filename, int $line): FilePosition
    {
        $sourceMapInfo = $this->sourceMapExtractor->extractFromFile($filename);

        return new FilePosition(
            $this->extractOriginalFilename($sourceMapInfo, $filename),
            $this->extractOriginalLine($sourceMapInfo, $line),
        );
    }

    private function extractOriginalFilename(SourceMapInformation $sourceMapInfo, string $filename): string
    {
        if (!str_contains($sourceMapInfo->filename(), '// ')) {
            return $filename;
        }

        return trim(substr($sourceMapInfo->filename(), 3));
    }

    private function extractOriginalLine(SourceMapInformation $sourceMapInfo, int $line): int
    {
        if (!str_contains($sourceMapInfo->filename(), '// ')
            || !str_contains($sourceMapInfo->sourceMap(), '// ')
        ) {
            return $line;
        }

        $mapping = trim(substr($sourceMapInfo->sourceMap(), 3));
        $sourceMapConsumer = new SourceMapConsumer($mapping);

        return ($sourceMapConsumer->getOriginalLine($line - 1) !== null && $sourceMapConsumer->getOriginalLine($line - 1) !== 0)
            ? $sourceMapConsumer->getOriginalLine($line - 1) ?? $line
            : $line;
    }
}
