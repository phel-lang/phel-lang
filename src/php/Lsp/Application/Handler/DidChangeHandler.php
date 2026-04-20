<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Document\ContentChangeApplier;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

final readonly class DidChangeHandler implements HandlerInterface
{
    public function __construct(
        private DiagnosticPublisher $publisher,
        private ParamsExtractor $params,
        private ContentChangeApplier $applier,
    ) {}

    public function method(): string
    {
        return 'textDocument/didChange';
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
        if (!$document instanceof Document) {
            return null;
        }

        $version = $this->params->version($params);
        $applied = $this->applier->apply($document, $params['contentChanges'] ?? null, $version);
        if (!$applied) {
            return null;
        }

        if ($this->publisher->shouldPublish($uri)) {
            $this->publisher->publish($document, $session->sink());
        }

        return null;
    }
}
