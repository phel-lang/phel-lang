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
use PhelTest\Support\CapturesCompilerWarningsTrait;
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
    use CapturesCompilerWarningsTrait;

    protected function tearDown(): void
    {
        $this->stopCapturingCompilerWarnings();
    }

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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_eval_response_reports_the_current_namespace_after_an_ns_form(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new NreplFacade();
        $facade->loadPhelNamespaces();

        $server = $facade->createSocketServer(0, '127.0.0.1');
        $server->start();

        $client = @stream_socket_client(
            sprintf('tcp://127.0.0.1:%d', $server->port()),
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

        $this->writeMessage($client, $encoder->encode(['op' => 'clone', 'id' => 'c1']));
        $this->pump($server);
        $clone = $this->readUntil($client, $decoder, $server, 1);
        $sessionId = $clone[0]['new-session'];

        // Editors send an empty init eval on connect to prime their
        // namespace state from the response: it is a no-op that must still
        // report the current `ns`, or the client's prompt stays unset.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'init',
            'session' => $sessionId,
            'code' => '',
        ]));
        $this->pump($server);
        $init = $this->readUntil($client, $decoder, $server, 1);
        self::assertCount(1, $init);
        self::assertSame('user', $init[0]['ns']);
        self::assertSame(['done'], $init[0]['status']);

        // A failed eval reports the current namespace too, so a client can
        // initialize its prompt even when the first eval errors (CIDER's
        // default Clojure init code does not evaluate under Phel).
        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'err',
            'session' => $sessionId,
            'code' => '(clojure.core/require @foo)',
        ]));
        $this->pump($server);
        $error = $this->readUntil($client, $decoder, $server, 2);
        self::assertSame('user', $error[0]['ns']);
        self::assertContains('eval-error', $error[0]['status']);

        // The session starts in the `user` namespace.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'e1',
            'session' => $sessionId,
            'code' => '(+ 1 2)',
        ]));
        $this->pump($server);
        $first = $this->readUntil($client, $decoder, $server, 2);
        $firstValue = $this->firstWithKey($first, 'value');
        self::assertNotNull($firstValue);
        self::assertSame('user', $firstValue['ns']);

        // Switching namespaces must be reflected in the `ns` response field,
        // which editor clients use to render the prompt.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'e2',
            'session' => $sessionId,
            'code' => '(ns foo)',
        ]));
        $this->pump($server);
        $nsSwitch = $this->readUntil($client, $decoder, $server, 2);
        $nsValue = $this->firstWithKey($nsSwitch, 'value');
        self::assertNotNull($nsValue);
        self::assertSame('foo', $nsValue['ns']);

        // ... and it sticks for subsequent evals in the same session.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'e3',
            'session' => $sessionId,
            'code' => '*ns*',
        ]));
        $this->pump($server);
        $after = $this->readUntil($client, $decoder, $server, 2);
        $afterValue = $this->firstWithKey($after, 'value');
        self::assertNotNull($afterValue);
        self::assertSame('foo', $afterValue['ns']);
        // Readable printing keeps the quotes around the string value.
        self::assertSame('"foo"', $afterValue['value']);

        fclose($client);
        $server->stop();
    }

    /**
     * Two editors on one server. Evaluation runs in a single process-wide
     * environment, so before #2906 client A's `(ns foo)` silently moved where
     * client B's next form compiled, and B's prompt jumped namespace with
     * nobody having asked.
     *
     * Definitions stay shared, which is the other half of the contract: the
     * registry is global, so B still sees a `def` A made.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_two_sessions_keep_independent_namespaces(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new NreplFacade();
        $facade->loadPhelNamespaces();

        $server = $facade->createSocketServer(0, '127.0.0.1');
        $server->start();

        $alice = $this->connect($server);
        $bob = $this->connect($server);

        $encoder = new BencodeEncoder();
        $aliceDecoder = new BencodeStreamDecoder();
        $bobDecoder = new BencodeStreamDecoder();

        $this->writeMessage($alice, $encoder->encode(['op' => 'clone', 'id' => 'ca']));
        $aliceSession = $this->readUntil($alice, $aliceDecoder, $server, 1)[0]['new-session'];

        $this->writeMessage($bob, $encoder->encode(['op' => 'clone', 'id' => 'cb']));
        $bobSession = $this->readUntil($bob, $bobDecoder, $server, 1)[0]['new-session'];

        self::assertNotSame($aliceSession, $bobSession);

        // Alice moves to her own namespace and defines something there.
        $this->writeMessage($alice, $encoder->encode([
            'op' => 'eval',
            'id' => 'a1',
            'session' => $aliceSession,
            'code' => '(ns alice.scratch) (def shared 42)',
        ]));
        $aliceNs = $this->firstWithKey($this->readUntil($alice, $aliceDecoder, $server, 2), 'value');
        self::assertNotNull($aliceNs);
        self::assertSame('alice.scratch', $aliceNs['ns']);

        // Bob never asked to leave `user`, so his eval must still land there.
        $this->writeMessage($bob, $encoder->encode([
            'op' => 'eval',
            'id' => 'b1',
            'session' => $bobSession,
            'code' => '*ns*',
        ]));
        $bobNs = $this->firstWithKey($this->readUntil($bob, $bobDecoder, $server, 2), 'value');
        self::assertNotNull($bobNs);
        self::assertSame('user', $bobNs['ns'], "Alice's (ns ...) must not move Bob's session");
        self::assertSame('"user"', $bobNs['value']);

        // Definitions are global, so Bob resolves Alice's `def` by its
        // qualified name even though he is in a different namespace.
        $this->writeMessage($bob, $encoder->encode([
            'op' => 'eval',
            'id' => 'b2',
            'session' => $bobSession,
            'code' => 'alice.scratch/shared',
        ]));
        $bobRead = $this->firstWithKey($this->readUntil($bob, $bobDecoder, $server, 2), 'value');
        self::assertNotNull($bobRead);
        self::assertSame('42', $bobRead['value']);
        self::assertSame('user', $bobRead['ns']);

        // Alice is still where she left off, after Bob's two evals ran in
        // `user` in between.
        $this->writeMessage($alice, $encoder->encode([
            'op' => 'eval',
            'id' => 'a2',
            'session' => $aliceSession,
            'code' => '*ns*',
        ]));
        $aliceAgain = $this->firstWithKey($this->readUntil($alice, $aliceDecoder, $server, 2), 'value');
        self::assertNotNull($aliceAgain);
        self::assertSame('alice.scratch', $aliceAgain['ns']);
        self::assertSame('"alice.scratch"', $aliceAgain['value']);

        fclose($alice);
        fclose($bob);
        $server->stop();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_it_returns_lookup_info_for_session_defined_symbols(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new NreplFacade();
        $facade->loadPhelNamespaces();

        $server = $facade->createSocketServer(0, '127.0.0.1');
        $server->start();

        $client = @stream_socket_client(
            sprintf('tcp://127.0.0.1:%d', $server->port()),
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

        $this->writeMessage($client, $encoder->encode(['op' => 'clone', 'id' => 'c1']));
        $this->pump($server);
        $clone = $this->readUntil($client, $decoder, $server, 1);
        $sessionId = $clone[0]['new-session'];

        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'e1',
            'session' => $sessionId,
            'code' => '(defn greet [n] (str "hello " n))',
        ]));
        $this->pump($server);
        $this->readUntil($client, $decoder, $server, 2);

        $this->writeMessage($client, $encoder->encode([
            'op' => 'lookup',
            'id' => 'l1',
            'session' => $sessionId,
            'sym' => 'greet',
        ]));
        $this->pump($server);
        $lookup = $this->readUntil($client, $decoder, $server, 1);

        $info = $lookup[0]['info'] ?? null;
        self::assertNotNull($info, 'lookup response should include info dict');
        self::assertSame('greet', $info['name']);
        self::assertSame('user', $info['ns']);
        self::assertSame('(greet n)', $info['arglists-str']);
        self::assertContains('done', $lookup[0]['status']);

        fclose($client);
        $server->stop();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_session_def_beats_an_injected_refer_of_the_same_name(): void
    {
        // The nREPL shares the prompt's refer injection
        // ({@see \Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReplReferInjector}),
        // so it inherited #2897: `doc` resolved to `phel.repl/doc` forever.
        $this->startCapturingCompilerWarnings();
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new NreplFacade();
        $facade->loadPhelNamespaces();

        $server = $facade->createSocketServer(0, '127.0.0.1');
        $server->start();

        $client = @stream_socket_client(
            sprintf('tcp://127.0.0.1:%d', $server->port()),
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

        $this->writeMessage($client, $encoder->encode(['op' => 'clone', 'id' => 'c1']));
        $this->pump($server);
        $sessionId = $this->readUntil($client, $decoder, $server, 1)[0]['new-session'];

        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'e1',
            'session' => $sessionId,
            'code' => '(def doc 1) doc',
        ]));
        $this->pump($server);
        $shadowed = $this->readUntil($client, $decoder, $server, 2);

        $value = $this->firstWithKey($shadowed, 'value');
        self::assertNotNull($value, 'eval response should include a value');
        self::assertSame('1', $value['value']);

        // A refer the session never redefines still resolves.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'e2',
            'session' => $sessionId,
            'code' => "(macroexpand-1 '(when true 1))",
        ]));
        $this->pump($server);
        $untouched = $this->firstWithKey($this->readUntil($client, $decoder, $server, 2), 'value');
        self::assertNotNull($untouched, 'eval response should include a value');
        self::assertStringContainsString('if', (string) $untouched['value']);

        $captured = $this->capturedCompilerWarnings();
        self::assertCount(1, $captured);
        self::assertStringContainsString("doc already refers to: #'phel.repl/doc", $captured[0]);

        fclose($client);
        $server->stop();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_it_runs_tests_and_reloads_over_a_live_socket(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new NreplFacade();
        $facade->loadPhelNamespaces();

        $server = $facade->createSocketServer(0, '127.0.0.1');
        $server->start();

        $client = @stream_socket_client(
            sprintf('tcp://127.0.0.1:%d', $server->port()),
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

        // describe advertises the new ops
        $this->writeMessage($client, $encoder->encode(['op' => 'describe', 'id' => 'd1']));
        $describe = $this->readUntil($client, $decoder, $server, 1);
        self::assertArrayHasKey('reload', $describe[0]['ops']);
        self::assertArrayHasKey('run-tests', $describe[0]['ops']);

        $this->writeMessage($client, $encoder->encode(['op' => 'clone', 'id' => 'c1']));
        $clone = $this->readUntil($client, $decoder, $server, 1);
        $sessionId = $clone[0]['new-session'];

        // Define a test namespace in the session.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'eval',
            'id' => 'e1',
            'session' => $sessionId,
            'code' => '(ns nrepl-sample-test (:require phel\\test :refer [deftest is])) '
                . '(deftest a-passing-test (is (= 1 1))) '
                . '(deftest a-failing-test (is (= 1 2)))',
        ]));
        $this->readUntil($client, $decoder, $server, 2);

        // run-tests over the whole namespace.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'run-tests',
            'id' => 'r1',
            'session' => $sessionId,
            'ns' => 'nrepl-sample-test',
        ]));
        $runTests = $this->readUntilDone($client, $decoder, $server);
        $value = $this->firstWithKey($runTests, 'value');
        self::assertNotNull($value, 'run-tests should return a summary value');
        self::assertStringContainsString(':pass 1', (string) $value['value']);
        self::assertStringContainsString(':fail 1', (string) $value['value']);
        self::assertNotNull($this->firstWithStatus($runTests, 'done'));

        // run-tests for a single test via the var param.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'run-tests',
            'id' => 'r2',
            'session' => $sessionId,
            'ns' => 'nrepl-sample-test',
            'var' => 'a-passing-test',
        ]));
        $runOne = $this->readUntilDone($client, $decoder, $server);
        $oneValue = $this->firstWithKey($runOne, 'value');
        self::assertNotNull($oneValue);
        self::assertStringContainsString(':pass 1', (string) $oneValue['value']);
        self::assertStringContainsString(':fail 0', (string) $oneValue['value']);

        // reload returns a vector of reloaded namespaces without erroring.
        $this->writeMessage($client, $encoder->encode([
            'op' => 'reload',
            'id' => 'rl1',
            'session' => $sessionId,
        ]));
        $reload = $this->readUntilDone($client, $decoder, $server);
        self::assertNotNull($this->firstWithStatus($reload, 'done'));
        self::assertNull(
            $this->firstWithStatus($reload, 'eval-error'),
            'reload should not report an eval error',
        );

        fclose($client);
        $server->stop();
    }

    /**
     * Reads frames until one carries a `done` status, returning everything
     * collected. Reporter output arrives as several `out` frames before the
     * value/done pair, so a fixed message count is not reliable here.
     *
     * @param resource $client
     *
     * @return list<array<string, mixed>>
     */
    private function readUntilDone($client, BencodeStreamDecoder $decoder, NreplSocketServer $server, int $timeoutMs = 3000): array
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

            if ($this->firstWithStatus($collected, 'done') !== null) {
                return $collected;
            }

            if ((int) (microtime(true) * 1000) - $start > $timeoutMs) {
                self::fail('Timed out waiting for a done frame.');
            }

            usleep(2000);
        }
    }

    /**
     * @return resource
     */
    private function connect(NreplSocketServer $server)
    {
        $client = @stream_socket_client(
            sprintf('tcp://127.0.0.1:%d', $server->port()),
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

        return $client;
    }

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
