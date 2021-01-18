<?php

declare(strict_types=1);

namespace Phel\Exceptions\Extractor;

use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapConsumer;
use Phel\Exceptions\Extractor\ReadModel\FilePosition;

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
        $extractedFilename = $sourceMapInfo->filename();
        $extractedSourceMap = $sourceMapInfo->sourceMap();

        $originalFilename = $filename;
        $originalLine = $line;

        if (0 === strpos($extractedFilename, '// ')) {
            $originalFilename = trim(substr($extractedFilename, 3));

            if (0 === strpos($extractedSourceMap, '// ')) {
                $mapping = trim(substr($extractedSourceMap, 3));

                $sourceMapConsumer = new SourceMapConsumer($mapping);
                $originalLine = ($sourceMapConsumer->getOriginalLine($line - 1)) ?: $line;
            }
        }

        return new FilePosition($originalFilename, $originalLine);
    }
}
