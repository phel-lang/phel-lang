<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

final readonly class DidOpenHandler implements HandlerInterface
{
    public function __construct(
        private DiagnosticPublisher $publisher,
        private ParamsExtractor $params,
    ) {}

    public function method(): string
    {
        return 'textDocument/didOpen';
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

        $languageId = $this->params->languageId($params);
        $version = $this->params->version($params);
        $text = $this->params->text($params);

        $document = $session->documents()->open($uri, $languageId, $version, $text);
        $this->publisher->publishNow($document, $session->sink());

        return null;
    }
}
