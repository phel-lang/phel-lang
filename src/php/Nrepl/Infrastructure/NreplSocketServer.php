<?php

declare(strict_types=1);

namespace Phel\Nrepl\Infrastructure;

use Closure;
use Fiber;
use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Domain\Transport\ClientConnection;
use RuntimeException;
use Throwable;

use function fclose;
use function is_resource;
use function sprintf;
use function stream_context_create;
use function stream_set_blocking;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
use function strrpos;
use function substr;
use function usleep;

/**
 * TCP nREPL server. Accepts bencode-framed connections on a port and
 * dispatches op messages through OpDispatcher.
 *
 * Uses one Fiber per connected client. The main accept loop yields between
 * iterations so Fibers can run cooperatively without blocking each other.
 */
final class NreplSocketServer
{
    /** @var resource|null */
    private mixed $server = null;

    private bool $running = false;

    /** @var list<Fiber> */
    private array $clientFibers = [];

    private readonly ?Closure $logger;

    public function __construct(
        private readonly OpDispatcher $dispatcher,
        private readonly int $port,
        private readonly string $host = '127.0.0.1',
        ?callable $logger = null,
    ) {
        $this->logger = $logger === null ? null : Closure::fromCallable($logger);
    }

    public function port(): int
    {
        if (is_resource($this->server)) {
            $name = stream_socket_get_name($this->server, false);
            if ($name !== false) {
                $colon = strrpos($name, ':');
                if ($colon !== false) {
                    return (int) substr($name, $colon + 1);
                }
            }
        }

        return $this->port;
    }

    public function start(): void
    {
        $errno = 0;
        $errstr = '';
        $context = stream_context_create();
        $address = sprintf('tcp://%s:%d', $this->host, $this->port);
        $server = @stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        if ($server === false) {
            throw new RuntimeException(sprintf(
                'Cannot bind nREPL server to %s (errno %d): %s',
                $address,
                $errno,
                $errstr,
            ));
        }

        stream_set_blocking($server, false);
        $this->server = $server;
        $this->running = true;
        $this->log(sprintf('nREPL server listening on %s:%d', $this->host, $this->port()));
    }

    public function stop(): void
    {
        $this->running = false;
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        if (is_resource($this->server)) {
            @fclose($this->server);
        }

        $this->server = null;
    }

    /**
     * Run the accept loop. Terminates when stop() is called or the socket
     * is closed. `$maxIterations` bounds test-driven runs; 0 means unbounded.
     */
    public function run(int $maxIterations = 0): void
    {
        if (!is_resource($this->server)) {
            throw new RuntimeException('Server not started.');
        }

        $iter = 0;
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        while ($this->running && is_resource($this->server)) {
            $this->acceptOnce();
            $this->stepFibers();

            ++$iter;
            if ($maxIterations > 0 && $iter >= $maxIterations) {
                break;
            }

            usleep(1000);
        }
    }

    /**
     * One tick of the accept loop: check for a new client, if any wire it
     * into a Fiber.
     */
    public function acceptOnce(): void
    {
        if (!is_resource($this->server)) {
            return;
        }

        $clientStream = @stream_socket_accept($this->server, 0);
        if ($clientStream === false) {
            return;
        }

        $connection = new ClientConnection($clientStream);
        $fiber = new Fiber(function (ClientConnection $conn): void {
            $this->serveClient($conn);
        });

        $fiber->start($connection);
        if (!$fiber->isTerminated()) {
            $this->clientFibers[] = $fiber;
        }
    }

    public function stepFibers(): void
    {
        $remaining = [];
        foreach ($this->clientFibers as $fiber) {
            $alive = $this->advanceFiber($fiber);
            if ($alive) {
                $remaining[] = $fiber;
            }
        }

        $this->clientFibers = $remaining;
    }

    /**
     * Returns true if the fiber is still alive and should be retained.
     */
    private function advanceFiber(Fiber $fiber): bool
    {
        if ($fiber->isTerminated()) {
            return false;
        }

        if (!$fiber->isSuspended()) {
            return true;
        }

        try {
            $fiber->resume();
        } catch (Throwable $throwable) {
            $this->log('Client fiber error: ' . $throwable->getMessage());
            return false;
        }

        return $fiber->isSuspended();
    }

    private function serveClient(ClientConnection $connection): void
    {
        while (!$connection->isClosed()) {
            $messages = $connection->readMessages();

            foreach ($messages as $message) {
                $responses = $this->dispatcher->dispatch($message);
                foreach ($responses as $response) {
                    $connection->send($response->payload);
                }
            }

            Fiber::suspend();
        }

        $connection->close();
    }

    private function log(string $message): void
    {
        if ($this->logger instanceof Closure) {
            ($this->logger)($message);
        }
    }
}
