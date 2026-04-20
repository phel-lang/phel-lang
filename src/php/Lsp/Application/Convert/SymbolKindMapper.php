<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Convert;

use Phel\Api\Transfer\Definition;

/**
 * Maps a Phel definition kind (`defn`, `defmacro`, `defstruct`, ...) to the
 * numeric `SymbolKind` enum from the LSP spec. A single source of truth keeps
 * `documentSymbol` and `workspace/symbol` responses consistent.
 *
 * Reference: LSP `SymbolKind` (1..26). Values used here:
 *  - 5  Class
 *  - 6  Method
 *  - 11 Interface
 *  - 12 Function
 *  - 13 Variable
 *  - 18 Struct
 */
final class SymbolKindMapper
{
    public const int FUNCTION = 12;

    public const int METHOD = 6;

    public const int CLASS_ = 5;

    public const int VARIABLE = 13;

    public const int INTERFACE = 11;

    public const int STRUCT = 18;

    public function fromDefinitionKind(string $kind): int
    {
        return match ($kind) {
            Definition::KIND_DEFN => self::FUNCTION,
            Definition::KIND_DEFMACRO => self::METHOD,
            Definition::KIND_DEFSTRUCT => self::STRUCT,
            Definition::KIND_DEFPROTOCOL, Definition::KIND_DEFINTERFACE => self::INTERFACE,
            Definition::KIND_DEFEXCEPTION => self::CLASS_,
            default => self::VARIABLE,
        };
    }
}
