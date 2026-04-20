<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Convert;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Location;
use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use PHPUnit\Framework\TestCase;

final class LocationConverterTest extends TestCase
{
    public function test_from_location_preserves_existing_file_uri(): void
    {
        $converter = $this->converter();
        $location = new Location('file:///x.phel', 2, 3, 2, 10);

        $result = $converter->fromLocation($location);

        self::assertSame('file:///x.phel', $result['uri']);
        self::assertSame(['line' => 1, 'character' => 2], $result['range']['start']);
        self::assertSame(['line' => 1, 'character' => 9], $result['range']['end']);
    }

    public function test_from_location_promotes_bare_path_to_file_uri(): void
    {
        $converter = $this->converter();
        $location = new Location('/tmp/x.phel', 1, 1);

        $result = $converter->fromLocation($location);

        self::assertStringStartsWith('file:///', $result['uri']);
    }

    public function test_from_location_falls_back_to_single_column_range_when_end_missing(): void
    {
        $converter = $this->converter();
        $location = new Location('file:///x.phel', 5, 7);

        $result = $converter->fromLocation($location);

        // endLine/endCol default to 0, so LSP end becomes (line 4, character 7).
        self::assertSame(['line' => 4, 'character' => 6], $result['range']['start']);
        self::assertSame(['line' => 4, 'character' => 7], $result['range']['end']);
    }

    public function test_from_definition_uses_name_length_for_range(): void
    {
        $converter = $this->converter();
        $definition = new Definition(
            'core',
            'my-fn',
            'file:///x.phel',
            4,
            5,
            Definition::KIND_DEFN,
            signature: [],
            docstring: '',
            private: false,
        );

        $result = $converter->fromDefinition($definition);

        self::assertSame('file:///x.phel', $result['uri']);
        self::assertSame(['line' => 3, 'character' => 4], $result['range']['start']);
        // 'my-fn' is 5 chars, so end column is 5 + 5 = 10 (0-indexed: 9).
        self::assertSame(['line' => 3, 'character' => 9], $result['range']['end']);
    }

    public function test_from_definition_handles_empty_name_gracefully(): void
    {
        $converter = $this->converter();
        $definition = new Definition(
            '',
            '',
            '/tmp/x.phel',
            1,
            1,
            Definition::KIND_UNKNOWN,
            [],
            '',
            false,
        );

        $result = $converter->fromDefinition($definition);

        // Even an empty name produces a range of at least 1 character.
        self::assertSame(['line' => 0, 'character' => 0], $result['range']['start']);
        self::assertSame(['line' => 0, 'character' => 1], $result['range']['end']);
    }

    private function converter(): LocationConverter
    {
        return new LocationConverter(new PositionConverter(), new UriConverter());
    }
}
