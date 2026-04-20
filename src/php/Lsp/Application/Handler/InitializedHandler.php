<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

/**
 * Notification sent by the client after it has processed the initialize
 * response. Flips the session into ready state.
 */
final class InitializedHandler implements HandlerInterface
{
    public function method(): string
    {
        return 'initialized';
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
        $session->markInitialized();
        return null;
    }
}
