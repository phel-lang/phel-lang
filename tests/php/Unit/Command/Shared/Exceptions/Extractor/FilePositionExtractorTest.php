<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Shared\Exceptions\Extractor;

use Phel\Command\Shared\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Shared\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Command\Shared\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Shared\Exceptions\Extractor\SourceMapExtractorInterface;
use PHPUnit\Framework\TestCase;

final class FilePositionExtractorTest extends TestCase
{
    public function test_get_original(): void
    {
        $extractor = new FilePositionExtractor(
            $this->stubSourceMapExtractor()
        );

        $filename = '/example-module-name/file-name.phel';
        $line = 1;

        self::assertEquals(
            new FilePosition($filename, $line),
            $extractor->getOriginal($filename, $line)
        );
    }

    public function test_get_original_with_file_name_comment(): void
    {
        $extractor = new FilePositionExtractor(
            $this->stubSourceMapExtractor(
                '// file-name/comment'
            )
        );

        $filename = '/example-module-name/file-name.phel';
        $line = 1;

        self::assertEquals(
            new FilePosition('file-name/comment', $line),
            $extractor->getOriginal($filename, $line)
        );
    }

    private function stubSourceMapExtractor(
        string $filename = '',
        string $sourceMap = ''
    ): SourceMapExtractorInterface {
        $sourceMapExtractor = $this->createMock(SourceMapExtractorInterface::class);
        $sourceMapExtractor
            ->method('extractFromFile')
            ->willReturn(new SourceMapInformation($filename, $sourceMap));

        return $sourceMapExtractor;
    }
}
