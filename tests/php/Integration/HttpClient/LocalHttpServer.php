<?php

declare(strict_types=1);

namespace PhelTest\Integration\HttpClient;

use RuntimeException;

use function fclose;
use function fsockopen;
use function is_resource;
use function proc_open;
use function proc_terminate;
use function stream_socket_get_name;
use function stream_socket_server;
use function usleep;

/**
 * A `php -S` process serving router.php on a free loopback port. Two instances
 * are two origins.
 */
final class LocalHttpServer
{
    /** @var resource */
    private $process;

    private function __construct(public readonly int $port)
    {
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/router.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start php -S');
        }

        $this->process = $process;
        $this->waitUntilListening();
    }

    public static function start(): self
    {
        return new self(self::freePort());
    }

    public function url(string $path): string
    {
        return 'http://127.0.0.1:' . $this->port . $path;
    }

    public function stop(): void
    {
        proc_terminate($this->process);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0') ?: throw new RuntimeException('No free port');
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function waitUntilListening(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $connection = @fsockopen('127.0.0.1', $this->port);
            if (is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(20_000);
        }

        throw new RuntimeException('php -S did not start on port ' . $this->port);
    }
}
