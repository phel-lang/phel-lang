<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_array;
use function is_string;

final readonly class DidChangeHandler implements HandlerInterface
{
    public function __construct(
        private DiagnosticPublisher $publisher,
        private ParamsExtractor $params,
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

        $version = $this->params->version($params);

        $document = $session->documents()->get($uri);
        if (!$document instanceof Document) {
            return null;
        }

        $changes = $params['contentChanges'] ?? [];
        if (!is_array($changes)) {
            return null;
        }

        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            $text = is_string($change['text'] ?? null) ? $change['text'] : '';
            $range = $change['range'] ?? null;

            if (is_array($range) && $this->params->isValidRange($range)) {
                /** @var array{start: array{line: int, character: int}, end: array{line: int, character: int}} $range */
                $document->applyRange($range, $text);
            } else {
                $document->update($text, $version);
            }
        }

        $document->update($document->text, $version);

        if ($this->publisher->shouldPublish($uri)) {
            $this->publisher->publish($document, $session->sink());
        }

        return null;
    }
}
