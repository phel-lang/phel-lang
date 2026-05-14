<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\WorkerFrame;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function fseek;
use function fwrite;
use function str_repeat;

final class WorkerFrameTest extends TestCase
{
    public function test_round_trips_a_payload(): void
    {
        $encoded = WorkerFrame::encode(['ns' => 'phel.http', 'ok' => true, 'count' => 7]);

        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, $encoded);
        fseek($stream, 0);

        $decoded = WorkerFrame::readBlocking($stream);
        fclose($stream);

        self::assertSame(['ns' => 'phel.http', 'ok' => true, 'count' => 7], $decoded);
    }

    public function test_encodes_with_8_hex_header(): void
    {
        $encoded = WorkerFrame::encode([]);

        self::assertSame(9, WorkerFrame::headerSize());
        self::assertSame('00000002', substr($encoded, 0, 8));
        self::assertSame("\n", $encoded[8]);
        self::assertSame('[]', substr($encoded, 9));
    }

    public function test_handles_large_payload(): void
    {
        $big = str_repeat('x', 200_000);
        $encoded = WorkerFrame::encode(['blob' => $big]);

        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, $encoded);
        fseek($stream, 0);

        $decoded = WorkerFrame::readBlocking($stream);
        fclose($stream);

        self::assertSame($big, $decoded['blob']);
    }

    public function test_returns_null_at_eof(): void
    {
        $stream = fopen('php://memory', 'r+b');

        self::assertNull(WorkerFrame::readBlocking($stream));

        fclose($stream);
    }

    public function test_decodes_body_directly(): void
    {
        $decoded = WorkerFrame::decodeBody('{"a":1,"b":[2,3]}');

        self::assertSame(['a' => 1, 'b' => [2, 3]], $decoded);
    }
}
