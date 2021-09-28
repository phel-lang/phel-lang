<?php

declare(strict_types=1);

namespace Phel\Command\Shared\Exceptions\Extractor;

use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapConsumer;
use Phel\Runtime\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Runtime\Exceptions\Extractor\ReadModel\SourceMapInformation;

final class FilePositionExtractor implements FilePositionExtractorInterface
{
    private SourceMapExtractorInterface $sourceMapExtractor;

    public function __construct(SourceMapExtractorInterface $sourceMapExtractor)
    {
        $this->sourceMapExtractor = $sourceMapExtractor;
    }

    public function getOriginal(string $filename, int $line): FilePosition
    {
        $sourceMapInfo = $this->sourceMapExtractor->extractFromFile($filename);

        return new FilePosition(
            $this->extractOriginalFilename($sourceMapInfo, $filename),
            $this->extractOriginalLine($sourceMapInfo, $line)
        );
    }

    private function extractOriginalFilename(SourceMapInformation $sourceMapInfo, string $filename): string
    {
        if (false === strpos($sourceMapInfo->filename(), '// ')) {
            return $filename;
        }

        return trim(substr($sourceMapInfo->filename(), 3));
    }

    private function extractOriginalLine(SourceMapInformation $sourceMapInfo, int $line): int
    {
        if (false === strpos($sourceMapInfo->filename(), '// ')
            || false === strpos($sourceMapInfo->sourceMap(), '// ')
        ) {
            return $line;
        }

        $mapping = trim(substr($sourceMapInfo->sourceMap(), 3));
        $sourceMapConsumer = new SourceMapConsumer($mapping);

        return ($sourceMapConsumer->getOriginalLine($line - 1)) ?: $line;
    }
}
