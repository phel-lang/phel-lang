<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Convert;

/**
 * Phel source locations are 1-based {line, column}; LSP positions are
 * 0-based {line, character}. Encapsulating the translation in one place
 * keeps every handler honest about which convention it uses.
 */
final class PositionConverter
{
    /**
     * @return array{line: int, character: int}
     */
    public function toLspPosition(int $line, int $column): array
    {
        return [
            'line' => max(0, $line - 1),
            'character' => max(0, $column - 1),
        ];
    }

    /**
     * @return array{
     *     start: array{line: int, character: int},
     *     end: array{line: int, character: int},
     * }
     */
    public function toLspRange(int $startLine, int $startCol, int $endLine, int $endCol): array
    {
        $effectiveEndLine = $endLine > 0 ? $endLine : $startLine;
        $effectiveEndCol = $endCol > 0 ? $endCol : $startCol + 1;

        return [
            'start' => $this->toLspPosition($startLine, $startCol),
            'end' => $this->toLspPosition($effectiveEndLine, $effectiveEndCol),
        ];
    }
}
