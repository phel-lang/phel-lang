<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

use function is_array;
use function is_string;

final class DidCloseHandler implements HandlerInterface
{
    public function method(): string
    {
        return 'textDocument/didClose';
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
        if ($uri !== '') {
            $session->documents()->close($uri);
            $session->sink()->notify('textDocument/publishDiagnostics', [
                'uri' => $uri,
                'diagnostics' => [],
            ]);
        }

        return null;
    }
}
