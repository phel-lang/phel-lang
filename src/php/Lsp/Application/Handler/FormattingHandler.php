<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Formatter\FormatterFacade;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;
use Throwable;

use function count;
use function explode;
use function is_array;
use function is_string;
use function str_replace;
use function strlen;

final readonly class FormattingHandler implements HandlerInterface
{
    public function __construct(
        private FormatterFacade $formatter,
    ) {}

    public function method(): string
    {
        return 'textDocument/formatting';
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
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return [];
        }

        $uri = is_string($textDocument['uri'] ?? null) ? $textDocument['uri'] : '';
        if ($uri === '') {
            return [];
        }

        $document = $session->documents()->get($uri);
        if (!$document instanceof Document) {
            return [];
        }

        try {
            $formatted = $this->formatter->formatString($document->text, $uri);
        } catch (Throwable) {
            return [];
        }

        if ($formatted === $document->text) {
            return [];
        }

        return [$this->fullDocumentEdit($document, $formatted)];
    }

    /**
     * @return array{
     *     range: array{start: array{line: int, character: int}, end: array{line: int, character: int}},
     *     newText: string,
     * }
     */
    private function fullDocumentEdit(Document $document, string $newText): array
    {
        $normalized = str_replace("\r\n", "\n", $document->text);
        $lines = explode("\n", $normalized);
        $lastLineIndex = count($lines) - 1;
        $lastLineLength = strlen($lines[$lastLineIndex]);

        return [
            'range' => [
                'start' => ['line' => 0, 'character' => 0],
                'end' => ['line' => $lastLineIndex, 'character' => $lastLineLength],
            ],
            'newText' => $newText,
        ];
    }
}
