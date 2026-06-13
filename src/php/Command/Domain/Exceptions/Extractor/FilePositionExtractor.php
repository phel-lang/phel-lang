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
    ) {}

    public function getOriginal(string $filename, int $line): FilePosition
    {
        $sourceMapInfo = $this->sourceMapExtractor->extractFromFile($filename);

        if (!$sourceMapInfo->hasFilename()) {
            return new FilePosition($filename, $line);
        }

        return new FilePosition(
            $sourceMapInfo->filename(),
            $this->extractOriginalLine($sourceMapInfo, $line),
        );
    }

    public function getFileLineMap(string $filename): array
    {
        $sourceMapInfo = $this->sourceMapExtractor->extractFromFile($filename);

        if (!$sourceMapInfo->hasFilename() || !$sourceMapInfo->hasMappings()) {
            return ['filename' => '', 'lines' => []];
        }

        $offset = $sourceMapInfo->codeStartLine() - 1;
        $consumer = new SourceMapConsumer($sourceMapInfo->mappings());

        $lines = [];
        foreach ($consumer->getMappedLines() as $generatedLine => $phelLine) {
            $lines[$generatedLine + $offset] = $phelLine;
        }

        return ['filename' => $sourceMapInfo->filename(), 'lines' => $lines];
    }

    private function extractOriginalLine(SourceMapInformation $sourceMapInfo, int $line): int
    {
        if (!$sourceMapInfo->hasMappings()) {
            return $line;
        }

        $generatedLine = $line - ($sourceMapInfo->codeStartLine() - 1);

        if ($generatedLine < 1) {
            return $line;
        }

        $sourceMapConsumer = new SourceMapConsumer($sourceMapInfo->mappings());
        $originalLine = $sourceMapConsumer->getOriginalLine($generatedLine);

        if ($originalLine === null || $originalLine === 0) {
            return $line;
        }

        return $originalLine;
    }
}
