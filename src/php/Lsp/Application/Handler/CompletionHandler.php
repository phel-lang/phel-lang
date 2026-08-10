<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Convert\CompletionConverter;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\HandlerInterface;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Facade\ApiFacadeInterface;

use function max;
use function min;
use function preg_match;
use function strlen;
use function substr;

/**
 * @phpstan-import-type CompletionItem from CompletionConverter
 * @phpstan-import-type Position from PositionConverter
 * @phpstan-import-type Range from PositionConverter
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
        $variableRange = $this->variableTokenRange($document, $position);

        $items = [];
        foreach ($completions as $completion) {
            $items[] = $this->completions->fromCompletion($completion, $variableRange);
        }

        return [
            'isIncomplete' => false,
            'items' => $items,
        ];
    }

    /**
     * Span of the `$...` token the cursor sits in, or null when there is none.
     *
     * Only the token shape matters here: whether a `$` token is a completable
     * position at all is the Api's call, and the converter applies the range to
     * variable completions only. Recognising the surrounding `php/` a second
     * time would fork the interop grammar across two modules.
     *
     * The span runs to the end of the token rather than to the cursor, so
     * completing inside `php/$_SE|RVER` replaces the whole name instead of
     * leaving a `RVER` tail behind.
     *
     * @param Position $position
     *
     * @return Range|null
     */
    private function variableTokenRange(Document $document, array $position): ?array
    {
        $lineNumber = max(0, $position['line']);
        $lineText = $document->lineAt($lineNumber);
        if ($lineText === null) {
            return null;
        }

        $character = min(max(0, $position['character']), strlen($lineText));
        if (preg_match('/\$\w*$/', substr($lineText, 0, $character), $matches) !== 1) {
            return null;
        }

        $end = $character;
        if (preg_match('/^\w+/', substr($lineText, $character), $tail) === 1) {
            $end += strlen($tail[0]);
        }

        return [
            'start' => ['line' => $lineNumber, 'character' => $character - strlen($matches[0])],
            'end' => ['line' => $lineNumber, 'character' => $end],
        ];
    }
}
