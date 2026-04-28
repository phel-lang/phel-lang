<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Infrastructure\Daemon;

use Phel\Api\Infrastructure\Daemon\JsonRpcFraming;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonRpcFramingTest extends TestCase
{
    public function test_it_reads_a_newline_delimited_json_message(): void
    {
        $framing = new JsonRpcFraming();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '{"id":1,"method":"ping"}' . "\n");
        rewind($stream);

        $message = $framing->readMessage($stream);
        fclose($stream);

        self::assertSame(['id' => 1, 'method' => 'ping'], $message);
    }

    public function test_it_returns_null_when_stream_is_exhausted(): void
    {
        $framing = new JsonRpcFraming();
        $stream = fopen('php://memory', 'r+');
        rewind($stream);

        self::assertNull($framing->readMessage($stream));
        fclose($stream);
    }

    public function test_it_raises_on_invalid_json_payload(): void
    {
        $framing = new JsonRpcFraming();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "not-json\n");
        rewind($stream);

        try {
            $framing->readMessage($stream);
            self::fail('Expected RuntimeException');
        } catch (RuntimeException) {
            self::assertTrue(true);
        } finally {
            fclose($stream);
        }
    }

    public function test_it_writes_a_newline_delimited_response(): void
    {
        $framing = new JsonRpcFraming();
        $stream = fopen('php://memory', 'r+');
        $framing->writeMessage($stream, ['id' => 1, 'result' => 'ok']);
        rewind($stream);
        $line = fgets($stream);
        fclose($stream);

        self::assertSame('{"id":1,"result":"ok"}' . "\n", $line);
    }
}
