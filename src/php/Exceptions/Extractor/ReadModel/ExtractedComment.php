<?php

declare(strict_types=1);

namespace Phel\Exceptions\Extractor\ReadModel;

final class ExtractedComment
{
    private string $fileNameComment;
    private string $sourceMapComment;

    public function __construct(string $fileNameComment, string $sourceMapComment)
    {
        $this->fileNameComment = $fileNameComment;
        $this->sourceMapComment = $sourceMapComment;
    }

    public function getFileNameComment(): string
    {
        return $this->fileNameComment;
    }

    public function getSourceMapComment(): string
    {
        return $this->sourceMapComment;
    }
}
