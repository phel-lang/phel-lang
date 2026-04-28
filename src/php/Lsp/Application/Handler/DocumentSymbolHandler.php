<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\SymbolInformationBuilder;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

/**
 * Lists all top-level definitions in the open document. Uses the project
 * index when available; otherwise runs a single-file indexing pass so the
 * response still matches the current buffer contents.
 */
final readonly class DocumentSymbolHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private UriConverter $uris,
        private SymbolInformationBuilder $symbolBuilder,
        private ParamsExtractor $params,
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
        $uri = $this->params->uri($params);
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
            $symbols[] = $this->symbolBuilder->fromDefinition($def);
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
}
