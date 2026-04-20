<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Convert;

use Phel\Api\Transfer\Definition;
use Phel\Lsp\Application\Convert\SymbolKindMapper;
use PHPUnit\Framework\TestCase;

final class SymbolKindMapperTest extends TestCase
{
    public function test_maps_defn_to_lsp_function(): void
    {
        $mapper = new SymbolKindMapper();

        self::assertSame(SymbolKindMapper::FUNCTION, $mapper->fromDefinitionKind(Definition::KIND_DEFN));
    }

    public function test_maps_defmacro_to_lsp_method(): void
    {
        $mapper = new SymbolKindMapper();

        self::assertSame(SymbolKindMapper::METHOD, $mapper->fromDefinitionKind(Definition::KIND_DEFMACRO));
    }

    public function test_maps_defstruct_to_lsp_struct(): void
    {
        $mapper = new SymbolKindMapper();

        self::assertSame(SymbolKindMapper::STRUCT, $mapper->fromDefinitionKind(Definition::KIND_DEFSTRUCT));
    }

    public function test_maps_defprotocol_to_lsp_interface(): void
    {
        $mapper = new SymbolKindMapper();

        self::assertSame(SymbolKindMapper::INTERFACE, $mapper->fromDefinitionKind(Definition::KIND_DEFPROTOCOL));
    }

    public function test_maps_definterface_to_lsp_interface(): void
    {
        $mapper = new SymbolKindMapper();

        self::assertSame(SymbolKindMapper::INTERFACE, $mapper->fromDefinitionKind(Definition::KIND_DEFINTERFACE));
    }

    public function test_maps_defexception_to_lsp_class(): void
    {
        $mapper = new SymbolKindMapper();

        self::assertSame(SymbolKindMapper::CLASS_, $mapper->fromDefinitionKind(Definition::KIND_DEFEXCEPTION));
    }

    public function test_falls_back_to_variable_for_unknown_kinds(): void
    {
        $mapper = new SymbolKindMapper();

        self::assertSame(SymbolKindMapper::VARIABLE, $mapper->fromDefinitionKind('something-else'));
        self::assertSame(SymbolKindMapper::VARIABLE, $mapper->fromDefinitionKind(Definition::KIND_DEF));
        self::assertSame(SymbolKindMapper::VARIABLE, $mapper->fromDefinitionKind(Definition::KIND_UNKNOWN));
    }
}
