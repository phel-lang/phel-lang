<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Convert;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Location;

use function strlen;

/**
 * Convert Api Location/Definition value objects into LSP Location shapes.
 *
 * @phpstan-import-type Range from PositionConverter
 */
final readonly class LocationConverter
{
    public function __construct(
        private PositionConverter $positions,
        private UriConverter $uris,
    ) {}

    /**
     * @return array{uri: string, range: Range}
     */
    public function fromLocation(Location $location): array
    {
        return [
            'uri' => $this->uris->toClientUri($location->uri),
            'range' => $this->positions->toLspRange(
                $location->line,
                $location->col,
                $location->endLine > 0 ? $location->endLine : $location->line,
                $location->endCol > 0 ? $location->endCol : $location->col + 1,
            ),
        ];
    }

    /**
     * @return array{uri: string, range: Range}
     */
    public function fromDefinition(Definition $definition): array
    {
        $nameLen = strlen($definition->name);
        $endCol = $definition->col + max(1, $nameLen);

        return [
            'uri' => $this->uris->toClientUri($definition->uri),
            'range' => $this->positions->toLspRange(
                $definition->line,
                $definition->col,
                $definition->line,
                $endCol,
            ),
        ];
    }
}
