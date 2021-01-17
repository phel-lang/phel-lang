<?php

declare(strict_types=1);

namespace Phel\Exceptions\Extractor;

use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapConsumer;
use Phel\Exceptions\Extractor\ReadModel\FilePosition;

final class FilePositionExtractor implements FilePositionExtractorInterface
{
    private CommentExtractorInterface $commentExtractor;

    public function __construct(CommentExtractorInterface $commentExtractor)
    {
        $this->commentExtractor = $commentExtractor;
    }

    public function getOriginal(string $fileName, int $line): FilePosition
    {
        $comments = $this->commentExtractor->getExtractedComment($fileName);

        $fileNameComment = $comments->getFileNameComment();
        $sourceMapComment = $comments->getSourceMapComment();

        $originalFile = $fileName;
        $originalLine = $line;

        if (0 === strpos($fileNameComment, '// ')) {
            $originalFile = trim(substr($fileNameComment, 3));

            if (0 === strpos($sourceMapComment, '// ')) {
                $mapping = trim(substr($sourceMapComment, 3));

                $sourceMapConsumer = new SourceMapConsumer($mapping);
                $originalLine = ($sourceMapConsumer->getOriginalLine($line - 1)) ?: $line;
            }
        }

        return new FilePosition($originalFile, $originalLine);
    }
}
