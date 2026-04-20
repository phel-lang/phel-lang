<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Convert;

use Phel\Api\Transfer\Definition;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\SymbolInformationBuilder;
use Phel\Lsp\Application\Convert\SymbolKindMapper;
use Phel\Lsp\Application\Convert\UriConverter;
use PHPUnit\Framework\TestCase;

final class SymbolInformationBuilderTest extends TestCase
{
    public function test_basic_shape_from_definition(): void
    {
        $builder = $this->builder();
        $def = new Definition(
            'core',
            'plus',
            'file:///x.phel',
            5,
            3,
            Definition::KIND_DEFN,
            [],
            '',
            false,
        );

        $result = $builder->fromDefinition($def);

        self::assertSame('plus', $result['name']);
        self::assertSame(SymbolKindMapper::FUNCTION, $result['kind']);
        self::assertSame('file:///x.phel', $result['location']['uri']);
        self::assertArrayHasKey('range', $result['location']);
    }

    public function test_from_definition_promotes_bare_path_to_file_uri(): void
    {
        $builder = $this->builder();
        $def = new Definition(
            'core',
            'plus',
            '/tmp/x.phel',
            1,
            1,
            Definition::KIND_DEFN,
            [],
            '',
            false,
        );

        $result = $builder->fromDefinition($def);

        self::assertStringStartsWith('file:///', $result['location']['uri']);
    }

    public function test_range_uses_name_length_for_end_column(): void
    {
        $builder = $this->builder();
        $def = new Definition(
            'core',
            'abcd',
            'file:///x.phel',
            2,
            5,
            Definition::KIND_DEFN,
            [],
            '',
            false,
        );

        $result = $builder->fromDefinition($def);
        $range = $result['location']['range'];

        self::assertSame(['line' => 1, 'character' => 4], $range['start']);
        // col + name length(4) => 5+4 = 9 -> 8 zero-based.
        self::assertSame(['line' => 1, 'character' => 8], $range['end']);
    }

    public function test_with_container_adds_namespace_field(): void
    {
        $builder = $this->builder();
        $def = new Definition(
            'my-app\\core',
            'run',
            'file:///x.phel',
            1,
            1,
            Definition::KIND_DEFN,
            [],
            '',
            false,
        );

        $result = $builder->fromDefinitionWithContainer($def);

        self::assertSame('my-app\\core', $result['containerName']);
        self::assertSame('run', $result['name']);
    }

    public function test_with_container_defaults_to_empty_string_when_definition_has_no_namespace(): void
    {
        $builder = $this->builder();
        $def = new Definition(
            '',
            'root-fn',
            'file:///x.phel',
            1,
            1,
            Definition::KIND_DEFN,
            [],
            '',
            false,
        );

        $result = $builder->fromDefinitionWithContainer($def);

        self::assertSame('', $result['containerName']);
    }

    public function test_single_character_range_for_empty_name(): void
    {
        $builder = $this->builder();
        $def = new Definition(
            '',
            '',
            '/tmp/a.phel',
            1,
            1,
            Definition::KIND_DEFN,
            [],
            '',
            false,
        );

        $result = $builder->fromDefinition($def);
        $range = $result['location']['range'];

        self::assertSame(['line' => 0, 'character' => 0], $range['start']);
        self::assertSame(['line' => 0, 'character' => 1], $range['end']);
    }

    public function test_kind_falls_back_to_variable_for_non_function_kinds(): void
    {
        $builder = $this->builder();
        $def = new Definition(
            'ns',
            'x',
            'file:///x.phel',
            1,
            1,
            Definition::KIND_DEF,
            [],
            '',
            false,
        );

        $result = $builder->fromDefinition($def);

        self::assertSame(SymbolKindMapper::VARIABLE, $result['kind']);
    }

    private function builder(): SymbolInformationBuilder
    {
        return new SymbolInformationBuilder(
            new PositionConverter(),
            new UriConverter(),
            new SymbolKindMapper(),
        );
    }
}
