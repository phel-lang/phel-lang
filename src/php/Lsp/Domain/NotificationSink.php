<?php

declare(strict_types=1);

namespace Phel\Lsp\Domain;

/**
 * Abstraction for handlers that need to push notifications back to the client
 * (e.g. diagnostics, log messages). The concrete implementation wires this
 * through the transport; unit tests can capture notifications in-memory.
 */
interface NotificationSink
{
    /**
     * @param array<string, mixed> $params
     */
    public function notify(string $method, array $params): void;
}
