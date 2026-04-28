<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\CompletionConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

final readonly class CompletionHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
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
     */
    public function handle(array $params, Session $session): mixed
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

        $items = [];
        foreach ($completions as $completion) {
            $items[] = $this->completions->fromCompletion($completion);
        }

        return [
            'isIncomplete' => false,
            'items' => $items,
        ];
    }
}
