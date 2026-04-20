<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_string;
use function str_contains;
use function strlen;
use function strtolower;

final readonly class WorkspaceSymbolHandler implements HandlerInterface
{
    public function __construct(
        private PositionConverter $positions,
        private UriConverter $uris,
    ) {}

    public function method(): string
    {
        return 'workspace/symbol';
    }

    public function isNotification(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function handle(array $params, Session $session): mixed
    {
        $index = $session->projectIndex();
        if (!$index instanceof ProjectIndex) {
            return [];
        }

        $query = is_string($params['query'] ?? null) ? strtolower($params['query']) : '';

        $symbols = [];
        foreach ($index->definitions as $def) {
            if ($query !== '' && !str_contains(strtolower($def->name), $query)) {
                continue;
            }

            $symbols[] = $this->toSymbolInformation($def);
        }

        return $symbols;
    }

    /**
     * @return array{
     *     name: string,
     *     kind: int,
     *     containerName: string,
     *     location: array{
     *         uri: string,
     *         range: array{start: array{line: int, character: int}, end: array{line: int, character: int}}
     *     }
     * }
     */
    private function toSymbolInformation(Definition $def): array
    {
        $endCol = $def->col + max(1, strlen($def->name));

        return [
            'name' => $def->name,
            'kind' => $this->lspSymbolKind($def->kind),
            'containerName' => $def->namespace,
            'location' => [
                'uri' => $this->uris->isFileUri($def->uri) ? $def->uri : $this->uris->fromFilePath($def->uri),
                'range' => $this->positions->toLspRange($def->line, $def->col, $def->line, $endCol),
            ],
        ];
    }

    private function lspSymbolKind(string $kind): int
    {
        return match ($kind) {
            Definition::KIND_DEFN => 12,
            Definition::KIND_DEFMACRO => 6,
            Definition::KIND_DEFSTRUCT => 18,
            Definition::KIND_DEFPROTOCOL, Definition::KIND_DEFINTERFACE => 11,
            Definition::KIND_DEFEXCEPTION => 5,
            default => 13,
        };
    }
}
