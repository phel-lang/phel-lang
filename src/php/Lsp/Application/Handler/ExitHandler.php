<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

/**
 * Handles the LSP `exit` notification: instructs the server to terminate.
 * Per the LSP lifecycle this is sent after a `shutdown` request (see
 * {@see ShutdownHandler}); unlike `shutdown` it is a notification (no response),
 * hence {@see self::isNotification()} returns true.
 */
final class ExitHandler implements HandlerInterface
{
    public function method(): string
    {
        return 'exit';
    }

    public function isNotification(): bool
    {
        return true;
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
