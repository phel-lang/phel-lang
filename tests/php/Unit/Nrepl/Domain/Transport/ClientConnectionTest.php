<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Transport;

use Fiber;
use Phel\Nrepl\Domain\Bencode\BencodeStreamDecoder;
use Phel\Nrepl\Domain\Transport\ClientConnection;
use Phel\Nrepl\Infrastructure\ClientFiberPool;
use PHPUnit\Framework\TestCase;

use function function_exists;

final class ClientConnectionTest extends TestCase
{
    public function test_closing_a_connection_while_sending_lets_its_fiber_finish(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false || !function_exists('socket_import_stream')) {
            self::markTestSkipped('Needs Unix socket pairs and ext-sockets.');
        }

        [$writer, $reader] = $pair;
        socket_set_option(socket_import_stream($writer), SOL_SOCKET, SO_SNDBUF, 4096);
        $connection = new ClientConnection($writer);
        $fiber = new Fiber(static function () use ($connection): void {
            $connection->send(['value' => str_repeat('x', 1048576)]);
        });
        try {
            $fiber->start();
            self::assertTrue($fiber->isSuspended());
            $connection->close();
            $fiber->resume();
            self::assertTrue($fiber->isTerminated());
            self::assertTrue($connection->isClosed());
        } finally {
            $connection->close();
            fclose($reader);
        }
    }

    public function test_partial_writes_yield_and_preserve_complete_frames(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false || !function_exists('socket_import_stream')) {
            self::markTestSkipped('Needs Unix socket pairs and ext-sockets.');
        }

        [$writer, $reader] = $pair;
        socket_set_option(socket_import_stream($writer), SOL_SOCKET, SO_SNDBUF, 4096);
        stream_set_blocking($reader, false);
        $connection = new ClientConnection($writer);
        $payload = ['value' => str_repeat('x', 1048576)];
        $done = ['status' => ['done']];
        $fiber = new Fiber(static function () use ($connection, $payload, $done): void {
            $connection->send($payload);
            $connection->send($done);
        });

        try {
            $fiber->start();
            self::assertTrue($fiber->isSuspended(), 'A full socket must yield instead of discarding bytes.');
            $otherPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertNotFalse($otherPair);
            [$otherWriter, $otherReader] = $otherPair;
            stream_set_blocking($otherReader, false);
            $otherConnection = new ClientConnection($otherWriter);
            $otherFiber = new Fiber(static function () use ($otherConnection, $done): void {
                Fiber::suspend();
                $otherConnection->send($done);
            });
            $otherFiber->start();
            $pool = new ClientFiberPool();
            $pool->add($fiber);
            $pool->add($otherFiber);
            $pool->step();
            try {
                self::assertTrue($otherFiber->isTerminated());
                self::assertSame('d6:statusl4:doneee', stream_get_contents($otherReader));
                self::assertTrue($fiber->isSuspended());
            } finally {
                $otherConnection->close();
                fclose($otherReader);
            }

            $received = '';
            for ($i = 0; $i < 1024; ++$i) {
                $received .= fread($reader, 65536);
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                } elseif ($fiber->isTerminated()) {
                    $received .= stream_get_contents($reader);
                    break;
                }
            }

            self::assertTrue($fiber->isTerminated());
            $decoder = new BencodeStreamDecoder();
            $decoder->feed($received);
            self::assertSame([$payload, $done], $decoder->drain());
        } finally {
            $connection->close();
            fclose($reader);
        }
    }
}
