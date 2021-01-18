<?php

declare(strict_types=1);

namespace PhelTest\Unit\Exceptions\Extractor;

use Phel\Exceptions\Extractor\FilePositionExtractor;
use Phel\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Exceptions\Extractor\SourceMapExtractorInterface;
use PHPUnit\Framework\TestCase;

final class FilePositionExtractorTest extends TestCase
{
    public function testGetOriginal(): void
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

    public function testGetOriginalWithFileNameComment(): void
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
