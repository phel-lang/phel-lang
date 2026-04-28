<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

final readonly class DidCloseHandler implements HandlerInterface
{
    public function __construct(
        private ParamsExtractor $params,
    ) {}

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
        $uri = $this->params->uri($params);
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
