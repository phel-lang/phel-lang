<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Infrastructure;

use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Infrastructure\NreplSocketServer;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function pcntl_alarm;
use function pcntl_async_signals;
use function pcntl_signal;

use const SIG_DFL;
use const SIGALRM;

final class NreplSocketServerTest extends TestCase
{
    public function test_stop_outside_the_loop_closes_the_socket_at_once(): void
    {
        $server = new NreplSocketServer(new OpDispatcher(), 0);
        $server->start();

        $listening = $server->port();

        $server->stop();

        self::assertNotSame(0, $listening);
        self::assertSame(0, $server->port());
    }

    /**
     * A SIGTERM handler calls stop() between any two statements of the accept
     * loop. Closing the socket there made the next stream_socket_accept() throw
     * a TypeError, so `phel nrepl` exited 1 instead of shutting down cleanly.
     */
    public function test_stop_from_a_signal_handler_lets_the_loop_end_and_close_the_socket(): void
    {
        if (!function_exists('pcntl_signal')) {
            self::markTestSkipped('Needs ext-pcntl.');
        }

        $server = new NreplSocketServer(new OpDispatcher(), 0);
        $server->start();

        $listening = $server->port();
        self::assertGreaterThan(0, $listening);
        $portAfterStop = null;

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function () use ($server, &$portAfterStop): void {
            $server->stop();
            $portAfterStop = $server->port();
        });

        try {
            pcntl_alarm(1);
            $server->run();
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_DFL);
        }

        self::assertSame($listening, $portAfterStop);
        self::assertSame(0, $server->port());
    }
}
