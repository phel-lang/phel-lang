<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_array;
use function is_int;
use function is_string;

final readonly class DidChangeHandler implements HandlerInterface
{
    public function __construct(
        private DiagnosticPublisher $publisher,
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
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return null;
        }

        $uri = is_string($textDocument['uri'] ?? null) ? $textDocument['uri'] : '';
        if ($uri === '') {
            return null;
        }

        $version = is_int($textDocument['version'] ?? null) ? $textDocument['version'] : 0;

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

            if (is_array($range) && $this->isValidRange($range)) {
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

    /**
     * @param array<string, mixed> $range
     */
    private function isValidRange(array $range): bool
    {
        $start = $range['start'] ?? null;
        $end = $range['end'] ?? null;

        return is_array($start)
            && is_array($end)
            && is_int($start['line'] ?? null)
            && is_int($start['character'] ?? null)
            && is_int($end['line'] ?? null)
            && is_int($end['character'] ?? null);
    }
}
