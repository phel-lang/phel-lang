<?php

declare(strict_types=1);

namespace Phel\Exceptions\Extractor;

use Phel\Exceptions\Extractor\ReadModel\ExtractedComment;

final class SourceMapExtractor implements CommentExtractorInterface
{
    public function getExtractedComment(string $fileName): ExtractedComment
    {
        $f = fopen($fileName, 'r');
        $phpPrefix = fgets($f);
        $fileNameComment = fgets($f);
        $sourceMapComment = fgets($f);

        return new ExtractedComment($fileNameComment, $sourceMapComment);
    }
}
