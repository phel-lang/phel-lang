<?php

declare(strict_types=1);

namespace PhelTest\Unit\Exceptions\Extractor;

use Phel\Exceptions\Extractor\CommentExtractorInterface;
use Phel\Exceptions\Extractor\FilePositionExtractor;
use Phel\Exceptions\Extractor\ReadModel\ExtractedComment;
use Phel\Exceptions\Extractor\ReadModel\FilePosition;
use PHPUnit\Framework\TestCase;

final class FilePositionExtractorTest extends TestCase
{
    public function testGetOriginal(): void
    {
        $extractor = new FilePositionExtractor(
            $this->stubCommentExtractor()
        );

        $fileName = '/example-module-name/file-name.phel';
        $line = 1;

        self::assertEquals(
            new FilePosition($fileName, $line),
            $extractor->getOriginal($fileName, $line)
        );
    }

    public function testGetOriginalWithFileNameComment(): void
    {
        $extractor = new FilePositionExtractor(
            $this->stubCommentExtractor(
                '// file-name/comment'
            )
        );

        $fileName = '/example-module-name/file-name.phel';
        $line = 1;

        self::assertEquals(
            new FilePosition('file-name/comment', $line),
            $extractor->getOriginal($fileName, $line)
        );
    }

    private function stubCommentExtractor(
        string $fileNameComment = '',
        string $sourceMapComment = ''
    ): CommentExtractorInterface {
        $commentExtractor = $this->createMock(CommentExtractorInterface::class);
        $commentExtractor
            ->method('getExtractedComment')
            ->willReturn(new ExtractedComment($fileNameComment, $sourceMapComment));

        return $commentExtractor;
    }
}
