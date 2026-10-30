<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

use RuntimeException;

use function fflush;
use function fwrite;
use function json_encode;
use function sprintf;
use function strlen;

/**
 * Writes a JSON-RPC 2.0 message using LSP's Content-Length framing.
 *
 *   Content-Length: <n>\r\n\r\n<body>
 *
 * @internal
 */
final class MessageWriter
{
    /**
     * @param resource             $stream
     * @param array<string, mixed> $message
     */
    public function write($stream, array $message): void
    {
        $json = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $frame = sprintf("Content-Length: %d\r\n\r\n%s", strlen($json), $json);
        if (@fwrite($stream, $frame) !== strlen($frame) || !@fflush($stream)) {
            throw new RuntimeException('Could not write LSP response');
        }
    }
}
