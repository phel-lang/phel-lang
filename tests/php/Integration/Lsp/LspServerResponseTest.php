<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\HandlerInterface;
use Phel\Lsp\Application\Rpc\LspServer;
use Phel\Lsp\Application\Rpc\MessageReader;
use Phel\Lsp\Application\Rpc\MessageWriter;
use Phel\Lsp\Application\Rpc\RequestDispatcher;
use Phel\Lsp\Application\Rpc\ResponseBuilder;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LspServerResponseTest extends TestCase
{
    #[DataProvider('badResponses')]
    public function test_a_bad_response_does_not_stop_the_next_request(string $kind): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        $writer = new MessageWriter();
        $writer->write($input, ['id' => 1, 'method' => 'textDocument/hover', 'params' => ['kind' => $kind]]);
        $writer->write($input, ['id' => 2, 'method' => 'textDocument/hover']);
        rewind($input);

        self::assertSame(0, $this->server()->serve($input, $output));
        rewind($output);
        $reader = new MessageReader();
        $first = $reader->read($output);
        self::assertSame(1, $first['id']);
        if ($kind === 'utf8') {
            self::assertSame("bad\u{FFFD}", $first['result']['contents']);
        } else {
            self::assertSame(ResponseBuilder::INTERNAL_ERROR, $first['error']['code']);
        }

        $second = $reader->read($output);
        self::assertSame(2, $second['id']);
        self::assertSame('good', $second['result']['contents']);
        fclose($input);
        fclose($output);
    }

    public static function badResponses(): iterable
    {
        yield 'invalid utf8' => ['utf8'];
        yield 'unencodable value' => ['nan'];
    }

    public function test_a_closed_output_stops_without_retrying_an_error_write(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        new MessageWriter()->write($input, ['id' => 1, 'method' => 'textDocument/hover']);
        rewind($input);
        fclose($output);
        self::assertSame(1, $this->server()->serve($input, $output));
        fclose($input);
    }

    public function test_parse_errors_leave_the_next_request_serviceable(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        fwrite($input, "Content-Length: 1\r\n\r\n{");
        new MessageWriter()->write($input, ['id' => 2, 'method' => 'textDocument/hover']);
        rewind($input);

        self::assertSame(0, $this->server()->serve($input, $output));
        rewind($output);
        $reader = new MessageReader();
        self::assertSame(ResponseBuilder::PARSE_ERROR, $reader->read($output)['error']['code']);
        self::assertSame(2, $reader->read($output)['id']);
        fclose($input);
        fclose($output);
    }

    public function test_parse_error_with_closed_output_exits_cleanly(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        fwrite($input, "Content-Length: 1\r\n\r\n{");
        rewind($input);
        fclose($output);

        self::assertSame(1, $this->server()->serve($input, $output));
        fclose($input);
    }

    private function server(): LspServer
    {
        $responses = new ResponseBuilder();
        $dispatcher = new RequestDispatcher($responses);
        $dispatcher->register(new class() implements HandlerInterface {
            public function method(): string
            {
                return 'textDocument/hover';
            }

            public function isNotification(): bool
            {
                return false;
            }

            public function handle(array $params, Session $session): array
            {
                return ['contents' => match ($params['kind'] ?? '') {
                    'utf8' => "bad\xE9",
                    'nan' => NAN,
                    default => 'good',
                }];
            }
        });
        $session = new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });

        return new LspServer(new MessageReader(), new MessageWriter(), $dispatcher, $responses, $session);
    }
}
