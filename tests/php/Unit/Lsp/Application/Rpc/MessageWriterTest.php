<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Rpc;

use Phel\Lsp\Application\Rpc\MessageReader;
use Phel\Lsp\Application\Rpc\MessageWriter;
use PHPUnit\Framework\TestCase;

use function fopen;
use function rewind;
use function stream_get_contents;

final class MessageWriterTest extends TestCase
{
    public function test_invalid_utf8_is_replaced_without_breaking_the_next_frame(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        $writer = new MessageWriter();
        $writer->write($stream, ['id' => 1, 'result' => "bad\xE9"]);
        $writer->write($stream, ['id' => 2, 'result' => 'good']);
        rewind($stream);
        $reader = new MessageReader();
        self::assertSame(['id' => 1, 'result' => "bad\u{FFFD}"], $reader->read($stream));
        self::assertSame(['id' => 2, 'result' => 'good'], $reader->read($stream));
    }

    public function test_it_prefixes_body_with_content_length_header(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);

        new MessageWriter()->write($stream, ['method' => 'ping']);

        rewind($stream);
        $contents = stream_get_contents($stream);
        self::assertIsString($contents);
        self::assertSame("Content-Length: 17\r\n\r\n{\"method\":\"ping\"}", $contents);
    }

    public function test_round_trip_with_reader(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);

        $payload = ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['ok' => true]];
        new MessageWriter()->write($stream, $payload);

        rewind($stream);
        $reader = new MessageReader();
        $roundTripped = $reader->read($stream);

        self::assertSame($payload, $roundTripped);
    }
}
