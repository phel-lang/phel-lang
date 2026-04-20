<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_array;
use function is_int;
use function is_string;

final readonly class DidOpenHandler implements HandlerInterface
{
    public function __construct(
        private DiagnosticPublisher $publisher,
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
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return null;
        }

        $uri = is_string($textDocument['uri'] ?? null) ? $textDocument['uri'] : '';
        if ($uri === '') {
            return null;
        }

        $languageId = is_string($textDocument['languageId'] ?? null) ? $textDocument['languageId'] : 'phel';
        $version = is_int($textDocument['version'] ?? null) ? $textDocument['version'] : 0;
        $text = is_string($textDocument['text'] ?? null) ? $textDocument['text'] : '';

        $document = $session->documents()->open($uri, $languageId, $version, $text);
        $this->publisher->publishNow($document, $session->sink());

        return null;
    }
}
