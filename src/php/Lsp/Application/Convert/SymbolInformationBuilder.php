<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Convert;

use Phel\Shared\Api\Definition;

use function max;
use function strlen;

/**
 * Builds the LSP `SymbolInformation` object shared between
 * `textDocument/documentSymbol` and `workspace/symbol`. Centralising the
 * construction keeps both responses in sync when the spec's fields drift.
 *
 * @phpstan-import-type LspLocation from PositionConverter
 *
 * @internal
 */
final readonly class SymbolInformationBuilder
{
    public function __construct(
        private PositionConverter $positions,
        private UriConverter $uris,
        private SymbolKindMapper $symbolKind,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     kind: int,
     *     location: LspLocation
     * }
     */
    public function fromDefinition(Definition $def): array
    {
        $endCol = $def->col + max(1, strlen($def->name));

        return [
            'name' => $def->name,
            'kind' => $this->symbolKind->fromDefinitionKind($def->kind),
            'location' => [
                'uri' => $this->uris->toClientUri($def->uri),
                'range' => $this->positions->toLspRange($def->line, $def->col, $def->line, $endCol),
            ],
        ];
    }

    /**
     * Variant including the `containerName` field used by
     * `workspace/symbol`.
     *
     * @return array{
     *     name: string,
     *     kind: int,
     *     containerName: string,
     *     location: LspLocation
     * }
     */
    public function fromDefinitionWithContainer(Definition $def): array
    {
        $base = $this->fromDefinition($def);

        return [
            'name' => $base['name'],
            'kind' => $base['kind'],
            'containerName' => $def->namespace,
            'location' => $base['location'],
        ];
    }
}
