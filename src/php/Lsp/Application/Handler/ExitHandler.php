<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

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
    public function handle(array $params, Session $session): mixed
    {
        $session->requestShutdown();
        return null;
    }
}
