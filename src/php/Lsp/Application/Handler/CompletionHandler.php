<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\CompletionConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_array;
use function is_int;
use function is_string;

final readonly class CompletionHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private CompletionConverter $completions,
    ) {}

    public function method(): string
    {
        return 'textDocument/completion';
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
        $position = $this->extractPosition($params);
        if ($uri === '' || $position === null) {
            return ['isIncomplete' => false, 'items' => []];
        }

        $document = $session->documents()->get($uri);
        if (!$document instanceof Document) {
            return ['isIncomplete' => false, 'items' => []];
        }

        $index = $session->projectIndex() ?? new ProjectIndex([], []);
        [$line, $col] = $document->oneBasedLineCol($position);

        $completions = $this->apiFacade->completeAtPoint($document->text, $line, $col, $index);

        $items = [];
        foreach ($completions as $completion) {
            $items[] = $this->completions->fromCompletion($completion);
        }

        return [
            'isIncomplete' => false,
            'items' => $items,
        ];
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

    /**
     * @param array<string, mixed> $params
     *
     * @return array{line: int, character: int}|null
     */
    private function extractPosition(array $params): ?array
    {
        $position = $params['position'] ?? null;
        if (!is_array($position)) {
            return null;
        }

        $line = $position['line'] ?? null;
        $character = $position['character'] ?? null;
        if (!is_int($line) || !is_int($character)) {
            return null;
        }

        return ['line' => $line, 'character' => $character];
    }
}
