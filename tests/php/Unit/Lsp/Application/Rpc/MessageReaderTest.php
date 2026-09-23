<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Rpc;

use Phel\Lsp\Application\Rpc\MessageReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fclose;
use function fopen;
use function fwrite;
use function rewind;
use function stream_socket_pair;

use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

final class MessageReaderTest extends TestCase
{
    public function test_it_reads_a_single_lsp_framed_message(): void
    {
        $stream = $this->memoryStreamWith("Content-Length: 17\r\n\r\n{\"method\":\"ping\"}");

        $reader = new MessageReader();
        $message = $reader->read($stream);

        self::assertSame(['method' => 'ping'], $message);
    }

    public function test_it_ignores_extra_headers(): void
    {
        $stream = $this->memoryStreamWith(
            "Content-Type: application/vscode-jsonrpc; charset=utf-8\r\n"
            . "Content-Length: 17\r\n\r\n{\"method\":\"ping\"}",
        );

        $reader = new MessageReader();
        $message = $reader->read($stream);

        self::assertSame(['method' => 'ping'], $message);
    }

    public function test_it_returns_null_at_eof(): void
    {
        $stream = $this->memoryStreamWith('');

        $reader = new MessageReader();
        $message = $reader->read($stream);

        self::assertNull($message);
    }

    public function test_it_throws_on_malformed_json(): void
    {
        $stream = $this->memoryStreamWith("Content-Length: 3\r\n\r\n{{{");

        $reader = new MessageReader();

        $this->expectException(RuntimeException::class);
        $reader->read($stream);
    }

    public function test_it_returns_empty_array_on_zero_content_length(): void
    {
        $stream = $this->memoryStreamWith("Content-Length: 0\r\n\r\n");

        $reader = new MessageReader();
        $message = $reader->read($stream);

        self::assertSame([], $message);
    }

    public function test_it_returns_empty_array_when_no_content_length_header_present(): void
    {
        $stream = $this->memoryStreamWith("Content-Type: application/vscode-jsonrpc\r\n\r\n");

        $reader = new MessageReader();
        $message = $reader->read($stream);

        self::assertSame([], $message);
    }

    public function test_it_throws_when_header_block_exceeds_maximum_size(): void
    {
        $header = '';
        for ($i = 0; $i < 40; ++$i) {
            $header .= "X-Custom-Header-{$i}: value\r\n";
        }

        $stream = $this->memoryStreamWith($header . "Content-Length: 17\r\n\r\n{\"method\":\"ping\"}");

        $reader = new MessageReader();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LSP header block exceeded maximum size.');
        $reader->read($stream);
    }

    public function test_it_reads_two_messages_back_to_back(): void
    {
        $stream = $this->memoryStreamWith(
            "Content-Length: 16\r\n\r\n{\"method\":\"one\"}"
            . "Content-Length: 16\r\n\r\n{\"method\":\"two\"}",
        );

        $reader = new MessageReader();
        $first = $reader->read($stream);
        $second = $reader->read($stream);

        self::assertSame('one', $first['method'] ?? null);
        self::assertSame('two', $second['method'] ?? null);
    }

    public function test_it_returns_heartbeat_not_eof_when_an_idle_connection_times_out(): void
    {
        // A connected-but-idle client (the common editor case) must not be
        // mistaken for a closed stream: read() returns [] (heartbeat), and a
        // subsequent message on the same stream still parses.
        [$server, $client] = $this->socketPair();

        $reader = new MessageReader();

        $idle = $reader->read($server);
        self::assertSame([], $idle, 'idle timeout should be a heartbeat, not EOF');

        fwrite($client, "Content-Length: 17\r\n\r\n{\"method\":\"ping\"}");
        $message = $this->readUntilMessage($reader, $server);
        self::assertSame(['method' => 'ping'], $message);

        fclose($client);
        fclose($server);
    }

    public function test_it_returns_null_when_the_client_closes_the_connection(): void
    {
        // A genuinely closed peer must still be reported as end-of-stream.
        [$server, $client] = $this->socketPair();
        fclose($client);

        $reader = new MessageReader();
        self::assertNull($reader->read($server));

        fclose($server);
    }

    /**
     * Poll past heartbeats until a framed message arrives.
     *
     * @param resource $stream
     *
     * @return array<string, mixed>|null
     */
    private function readUntilMessage(MessageReader $reader, $stream): ?array
    {
        for ($i = 0; $i < 50; ++$i) {
            $message = $reader->read($stream);
            if ($message === null || $message !== []) {
                return $message;
            }
        }

        return null;
    }

    /**
     * A blocking-but-idle stream pair: reading from one end times out (without
     * EOF) until the other end writes, which `php://memory` cannot emulate.
     *
     * @return array{0: resource, 1: resource}
     */
    private function socketPair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        self::assertNotFalse($pair);

        return [$pair[0], $pair[1]];
    }

    /**
     * @return resource
     */
    private function memoryStreamWith(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        if ($content !== '') {
            fwrite($stream, $content);
            rewind($stream);
        }

        return $stream;
    }
}
