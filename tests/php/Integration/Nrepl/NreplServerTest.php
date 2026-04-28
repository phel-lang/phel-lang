<?php

declare(strict_types=1);

namespace PhelTest\Integration\Nrepl;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Nrepl\Domain\Bencode\BencodeEncoder;
use Phel\Nrepl\Domain\Bencode\BencodeStreamDecoder;
use Phel\Nrepl\Infrastructure\NreplSocketServer;
use Phel\Nrepl\NreplFacade;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function count;
use function fclose;
use function fread;
use function fwrite;
use function in_array;
use function is_array;
use function sprintf;
use function stream_set_blocking;
use function stream_set_timeout;
use function stream_socket_client;
use function strlen;
use function usleep;

final class NreplServerTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_it_handles_describe_clone_eval_and_close_over_a_live_socket(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new NreplFacade();
        $facade->loadPhelNamespaces();

        $server = $facade->createSocketServer(0, '127.0.0.1');
        $server->start();

        $port = $server->port();

        $client = @stream_socket_client(
            sprintf('tcp://127.0.0.1:%d', $port),
            $errno,
            $errstr,
            2.0,
        );
        if ($client === false) {
            $server->stop();
            self::fail(sprintf('Could not connect to server: %s', $errstr));
        }

        stream_set_blocking($client, false);
        stream_set_timeout($client, 2);

        $encoder = new BencodeEncoder();
        $decoder = new BencodeStreamDecoder();

        // describe
        $this->writeMessage($client, $encoder->encode(['op' => 'describe', 'id' => 'd1']));
        $this->pump($server);
        $describe = $this->readUntil($client, $decoder, $server, 1);
        self::assertCount(1, $describe);
        self::assertSame('d1', $describe[0]['id']);
        self::assertContains('done', $describe[0]['status']);

        // clone
        $this->writeMessage($client, $encoder->encode(['op' => 'clone', 'id' => 'c1']));
        $this->pump($server);
        $clone = $this->readUntil($client, $decoder, $server, 1);
        $sessionId = $clone[0]['new-session'];
        self::assertNotEmpty($sessionId);

        // eval
        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'e1',
            'session' => $sessionId,
            'code' => '(+ 1 2)',
        ]));
        $this->pump($server);
        $eval = $this->readUntil($client, $decoder, $server, 2);

        $valueMsg = $this->firstWithKey($eval, 'value');
        self::assertNotNull($valueMsg);
        self::assertSame('3', $valueMsg['value']);

        $doneMsg = $this->firstWithStatus($eval, 'done');
        self::assertNotNull($doneMsg);

        // close
        $this->writeMessage($client, $encoder->encode([
            'op' => 'close',
            'id' => 'x1',
            'session' => $sessionId,
        ]));
        $this->pump($server);
        $close = $this->readUntil($client, $decoder, $server, 1);
        self::assertContains('session-closed', $close[0]['status']);

        fclose($client);
        $server->stop();
    }

    /**
     * @param resource $client
     */
    private function writeMessage($client, string $message): void
    {
        $written = 0;
        $length = strlen($message);
        while ($written < $length) {
            $bytes = @fwrite($client, substr($message, $written));
            if ($bytes === false) {
                throw new RuntimeException('Failed to write to client socket.');
            }

            if ($bytes === 0) {
                usleep(1000);
            } else {
                $written += $bytes;
            }
        }
    }

    /**
     * @param resource $client
     * @param mixed    $server
     *
     * @return list<array<string, mixed>>
     */
    private function readUntil($client, BencodeStreamDecoder $decoder, NreplSocketServer $server, int $minMessages, int $timeoutMs = 3000): array
    {
        $start = (int) (microtime(true) * 1000);
        $collected = [];

        while (true) {
            $this->pump($server);
            $chunk = @fread($client, 8192);
            if ($chunk !== false && $chunk !== '') {
                $decoder->feed($chunk);
                foreach ($decoder->drain() as $msg) {
                    if (is_array($msg)) {
                        $collected[] = $msg;
                    }
                }
            }

            if (count($collected) >= $minMessages) {
                return $collected;
            }

            $elapsed = (int) (microtime(true) * 1000) - $start;
            if ($elapsed > $timeoutMs) {
                self::fail(sprintf(
                    'Timed out waiting for %d messages (got %d).',
                    $minMessages,
                    count($collected),
                ));
            }

            usleep(2000);
        }
    }

    private function pump(NreplSocketServer $server): void
    {
        // Drive the server loop a few times without blocking.
        for ($i = 0; $i < 5; ++$i) {
            $server->acceptOnce();
            $server->stepFibers();
        }
    }

    /**
     * @param list<array<string, mixed>> $msgs
     *
     * @return array<string, mixed>|null
     */
    private function firstWithKey(array $msgs, string $key): ?array
    {
        foreach ($msgs as $msg) {
            if (isset($msg[$key])) {
                return $msg;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $msgs
     *
     * @return array<string, mixed>|null
     */
    private function firstWithStatus(array $msgs, string $status): ?array
    {
        foreach ($msgs as $msg) {
            if (isset($msg['status']) && is_array($msg['status']) && in_array($status, $msg['status'], true)) {
                return $msg;
            }
        }

        return null;
    }
}
