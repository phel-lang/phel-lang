<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Rpc;

use Phel\Lsp\Application\Rpc\MessageReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fopen;
use function fwrite;
use function rewind;

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
