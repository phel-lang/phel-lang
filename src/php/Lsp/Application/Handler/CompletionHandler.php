<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Convert\CompletionConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\HandlerInterface;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Shared\Api\Completion;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Facade\ApiFacadeInterface;

use function preg_match;
use function preg_split;
use function strlen;
use function substr;

/**
 * @phpstan-import-type CompletionItem from CompletionConverter
 *
 * @internal
 */
final readonly class CompletionHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacadeInterface $apiFacade,
        private CompletionConverter $completions,
        private ParamsExtractor $params,
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
     *
     * @return array{isIncomplete: bool, items: list<CompletionItem>}
     */
    public function handle(array $params, Session $session): array
    {
        $uri = $this->params->uri($params);
        $position = $this->params->position($params);
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
        $globalVariableRange = $this->globalVariableRange($document->text, $position);

        $items = [];
        foreach ($completions as $completion) {
            $item = $this->completions->fromCompletion($completion);
            if ($globalVariableRange !== null && $completion->kind === Completion::KIND_VARIABLE) {
                // Replace the whole `$...` token, including the sigil, while
                // retaining the preceding `php/` interop prefix.
                $item['textEdit'] = [
                    'range' => $globalVariableRange,
                    'newText' => $completion->label,
                ];
            }

            $items[] = $item;
        }

        return [
            'isIncomplete' => false,
            'items' => $items,
        ];
    }

    /**
     * @param array{line: int, character: int} $position
     *
     * @return array{start: array{line: int, character: int}, end: array{line: int, character: int}}|null
     */
    private function globalVariableRange(string $source, array $position): ?array
    {
        $lines = preg_split('/\r?\n/', $source) ?: [];
        $line = $lines[$position['line']] ?? null;
        if ($line === null) {
            return null;
        }

        $before = substr($line, 0, $position['character']);
        if (preg_match('/(?:^|[\s(\[{])php\/(\$\w*)$/', $before, $matches) !== 1) {
            return null;
        }

        return [
            'start' => [
                'line' => $position['line'],
                'character' => $position['character'] - strlen($matches[1]),
            ],
            'end' => $position,
        ];
    }
}
