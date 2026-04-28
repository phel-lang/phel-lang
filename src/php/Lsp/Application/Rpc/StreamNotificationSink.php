<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

use Phel\Lsp\Domain\NotificationSink;

/**
 * NotificationSink backed by a writable PHP stream + LSP MessageWriter.
 */
final class StreamNotificationSink implements NotificationSink
{
    /**
     * @param resource $stream
     */
    public function __construct(
        private readonly MessageWriter $writer,
        private readonly ResponseBuilder $responses,
        private $stream,
    ) {}

    public function notify(string $method, array $params): void
    {
        $this->writer->write($this->stream, $this->responses->notification($method, $params));
    }
}
