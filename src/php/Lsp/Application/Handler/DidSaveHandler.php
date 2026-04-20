<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function str_ends_with;
use function strtolower;

final readonly class DidSaveHandler implements HandlerInterface
{
    public function __construct(
        private DiagnosticPublisher $publisher,
        private ApiFacade $apiFacade,
        private UriConverter $uris,
        private ParamsExtractor $params,
    ) {}

    public function method(): string
    {
        return 'textDocument/didSave';
    }

    public function isNotification(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function handle(array $params, Session $session): mixed
    {
        $uri = $this->params->uri($params);
        if ($uri === '') {
            return null;
        }

        $document = $session->documents()->get($uri);
        if ($document instanceof Document) {
            $this->publisher->publishNow($document, $session->sink());
        }

        if ($this->shouldReindex($uri)) {
            $roots = $session->workspaceRoots();
            if ($roots !== []) {
                $session->setProjectIndex($this->apiFacade->indexProject($roots));
            }
        }

        return null;
    }

    private function shouldReindex(string $uri): bool
    {
        $path = $this->uris->toFilePath($uri);
        return str_ends_with(strtolower($path), '.phel');
    }
}
