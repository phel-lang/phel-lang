<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

/**
 * Handles the LSP `shutdown` request: marks the session as shutting down so the
 * server stops processing further requests, but does not terminate the process.
 * Per the LSP lifecycle, the client must follow up with an `exit` notification
 * (see {@see ExitHandler}) to actually end the server.
 */
final class ShutdownHandler implements HandlerInterface
{
    public function method(): string
    {
        return 'shutdown';
    }

    public function isNotification(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function handle(array $params, Session $session): null
    {
        $session->requestShutdown();
        return null;
    }
}
