<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use Phel\Lsp\Application\Rpc\MessageWriter;
use PHPUnit\Framework\TestCase;

use function dirname;
use function is_resource;
use function strlen;

final class DiagnosticsStdioTest extends TestCase
{
    public function test_idle_piped_stdin_publishes_the_last_unsaved_version(): void
    {
        $root = dirname(__DIR__, 4);
        $process = proc_open([PHP_BINARY, $root . '/bin/phel', 'lsp'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [...getenv(), 'PHEL_NO_OPCACHE_REEXEC' => '1']);
        self::assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $writer = new MessageWriter();
        $buffer = '';
        try {
            $writer->write($pipes[0], ['id' => 1, 'method' => 'initialize', 'params' => []]);
            $this->awaitMessage($pipes[1], $buffer, static fn(array $message): bool => ($message['id'] ?? null) === 1, 20.0);
            $uri = 'file:///project/trailing-unsaved.phel';
            $writer->write($pipes[0], ['method' => 'textDocument/didOpen', 'params' => ['textDocument' => ['uri' => $uri, 'languageId' => 'phel', 'version' => 1, 'text' => '(ns trailing)']]]);
            $this->awaitMessage($pipes[1], $buffer, static fn(array $message): bool => ($message['method'] ?? '') === 'textDocument/publishDiagnostics', 5.0);
            foreach (['first', 'second', 'latest'] as $i => $name) {
                $text = "(ns trailing)\n" . str_repeat("\n", $i) . '(let [' . $name . ' 1] :ok)';
                $writer->write($pipes[0], ['method' => 'textDocument/didChange', 'params' => ['textDocument' => ['uri' => $uri, 'version' => $i + 2], 'contentChanges' => [['text' => $text]]]]);
                if ($i < 2) {
                    usleep(50_000);
                }
            }

            $last = $this->awaitMessage($pipes[1], $buffer, static fn(array $message): bool => ($message['params']['version'] ?? null) === 4, 5.0);
            $unused = array_values(array_filter($last['params']['diagnostics'], static fn(array $diagnostic): bool => $diagnostic['code'] === 'phel/unused-binding'));
            self::assertCount(1, $unused);
            self::assertStringContainsString('latest', (string) $unused[0]['message']);
            self::assertSame(3, $unused[0]['range']['start']['line']);
            fclose($pipes[0]);
            unset($pipes[0]);
            $deadline = microtime(true) + 3.0;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }

                usleep(10_000);
            } while (microtime(true) < $deadline);

            self::assertFalse($status['running'], 'Server did not exit after stdin EOF');
            self::assertSame(0, $status['exitcode']);
            self::assertSame('', stream_get_contents($pipes[2]));
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }

            proc_close($process);
        }
    }

    private function awaitMessage($stream, string &$buffer, callable $matches, float $timeout): array
    {
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            while (preg_match('/^Content-Length: (\d+)\r\n\r\n/', $buffer, $header) === 1) {
                $length = (int) $header[1];
                $offset = strlen($header[0]);
                if (strlen($buffer) < $offset + $length) {
                    break;
                }

                $message = json_decode(substr($buffer, $offset, $length), true, 512, JSON_THROW_ON_ERROR);
                $buffer = substr($buffer, $offset + $length);
                if ($matches($message)) {
                    return $message;
                }
            }

            $read = [$stream];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 100_000) > 0) {
                $buffer .= fread($stream, 65_536);
            }
        }

        self::fail('No matching LSP message before the idle deadline');
    }
}
