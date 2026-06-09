<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Domain\Exceptions\Extractor;

use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Domain\Exceptions\Extractor\SourceMapExtractorInterface;
use PHPUnit\Framework\TestCase;

final class FilePositionExtractorTest extends TestCase
{
    public function test_returns_input_position_when_no_source_map(): void
    {
        $extractor = new FilePositionExtractor(
            $this->stubSourceMapExtractor(SourceMapInformation::none()),
        );

        self::assertEquals(
            new FilePosition('/example-module-name/file-name.php', 1),
            $extractor->getOriginal('/example-module-name/file-name.php', 1),
        );
    }

    public function test_replaces_filename_when_no_mappings_available(): void
    {
        $extractor = new FilePositionExtractor(
            $this->stubSourceMapExtractor(new SourceMapInformation('/src/main.phel', '')),
        );

        self::assertEquals(
            new FilePosition('/src/main.phel', 7),
            $extractor->getOriginal('/tmp/__phel_abc.php', 7),
        );
    }

    public function test_maps_line_through_mappings_relative_to_code_start(): void
    {
        // 'AACA' maps generated line 1 to original line 2; the generated code
        // starts at file line 4, so trace line 4 is generated line 1.
        $extractor = new FilePositionExtractor(
            $this->stubSourceMapExtractor(new SourceMapInformation('/src/main.phel', 'AACA', 4)),
        );

        self::assertEquals(
            new FilePosition('/src/main.phel', 2),
            $extractor->getOriginal('/tmp/__phel_abc.php', 4),
        );
    }

    public function test_keeps_line_when_mappings_have_no_entry_for_it(): void
    {
        $extractor = new FilePositionExtractor(
            $this->stubSourceMapExtractor(new SourceMapInformation('/src/main.phel', 'AACA', 4)),
        );

        self::assertEquals(
            new FilePosition('/src/main.phel', 9),
            $extractor->getOriginal('/tmp/__phel_abc.php', 9),
        );
    }

    public function test_keeps_line_when_trace_line_is_before_code_start(): void
    {
        $extractor = new FilePositionExtractor(
            $this->stubSourceMapExtractor(new SourceMapInformation('/src/main.phel', 'AACA', 4)),
        );

        self::assertEquals(
            new FilePosition('/src/main.phel', 2),
            $extractor->getOriginal('/tmp/__phel_abc.php', 2),
        );
    }

    private function stubSourceMapExtractor(SourceMapInformation $info): SourceMapExtractorInterface
    {
        $sourceMapExtractor = $this->createStub(SourceMapExtractorInterface::class);
        $sourceMapExtractor
            ->method('extractFromFile')
            ->willReturn($info);

        return $sourceMapExtractor;
    }
}
