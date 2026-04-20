<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_array;
use function is_string;
use function strlen;

/**
 * Lists all top-level definitions in the open document. Uses the project
 * index when available; otherwise runs a single-file indexing pass so the
 * response still matches the current buffer contents.
 */
final readonly class DocumentSymbolHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private PositionConverter $positions,
        private UriConverter $uris,
    ) {}

    public function method(): string
    {
        return 'textDocument/documentSymbol';
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
        $uri = $this->extractUri($params);
        if ($uri === '') {
            return [];
        }

        $document = $session->documents()->get($uri);
        if (!$document instanceof Document) {
            return [];
        }

        $definitions = $this->collectDefinitions($document, $session->projectIndex());

        $symbols = [];
        foreach ($definitions as $def) {
            $symbols[] = $this->toSymbolInformation($def);
        }

        return $symbols;
    }

    /**
     * @return list<Definition>
     */
    private function collectDefinitions(Document $document, ?ProjectIndex $index): array
    {
        $path = $this->uris->toFilePath($document->uri);

        $defs = [];
        if ($index instanceof ProjectIndex) {
            foreach ($index->definitions as $def) {
                if ($def->uri === $path || $def->uri === $document->uri) {
                    $defs[] = $def;
                }
            }
        }

        if ($defs !== []) {
            return $defs;
        }

        $index = $this->apiFacade->indexProject([$path]);
        $result = [];
        foreach ($index->definitions as $def) {
            $result[] = $def;
        }

        return $result;
    }

    /**
     * @return array{
     *     name: string,
     *     kind: int,
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
            'location' => [
                'uri' => $this->uris->isFileUri($def->uri) ? $def->uri : $this->uris->fromFilePath($def->uri),
                'range' => $this->positions->toLspRange($def->line, $def->col, $def->line, $endCol),
            ],
        ];
    }

    private function lspSymbolKind(string $kind): int
    {
        // LSP SymbolKind: 12=Function, 6=Method, 5=Class, 13=Variable, 11=Interface, 18=Struct
        return match ($kind) {
            Definition::KIND_DEFN => 12,
            Definition::KIND_DEFMACRO => 6,
            Definition::KIND_DEFSTRUCT => 18,
            Definition::KIND_DEFPROTOCOL, Definition::KIND_DEFINTERFACE => 11,
            Definition::KIND_DEFEXCEPTION => 5,
            default => 13,
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function extractUri(array $params): string
    {
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return '';
        }

        return is_string($textDocument['uri'] ?? null) ? $textDocument['uri'] : '';
    }
}
