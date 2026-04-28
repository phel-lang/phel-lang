<?php

declare(strict_types=1);

namespace Phel\Lsp\Domain;

use Phel\Lsp\Application\Session\Session;

interface HandlerInterface
{
    /**
     * LSP method name (e.g. "textDocument/hover").
     */
    public function method(): string;

    /**
     * Return true when the handler is for an LSP notification (no response).
     */
    public function isNotification(): bool;

    /**
     * Handle an LSP request/notification and return the `result` payload
     * (ignored for notifications).
     *
     * @param array<string, mixed> $params
     */
    public function handle(array $params, Session $session): mixed;
}
