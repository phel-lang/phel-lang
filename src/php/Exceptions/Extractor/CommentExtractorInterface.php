<?php

declare(strict_types=1);

namespace Phel\Exceptions\Extractor;

use Phel\Exceptions\Extractor\ReadModel\ExtractedComment;

interface CommentExtractorInterface
{
    public function getExtractedComment(string $fileName): ExtractedComment;
}
