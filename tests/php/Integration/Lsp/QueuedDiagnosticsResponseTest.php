<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lint\LintFacade;
use Phel\Lsp\Application\Convert\DiagnosticConverter;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Rpc\LspServer;
use Phel\Lsp\Application\Rpc\MessageReader;
use Phel\Lsp\Application\Rpc\MessageWriter;
use Phel\Lsp\Application\Rpc\RequestDispatcher;
use Phel\Lsp\Application\Rpc\ResponseBuilder;
use Phel\Lsp\Application\Rpc\StreamNotificationSink;
use Phel\Lsp\Application\Session\Session;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Facade\ApiFacadeInterface;
use PHPUnit\Framework\TestCase;

final class QueuedDiagnosticsResponseTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        GlobalEnvironmentSingleton::initializeNew();
    }

    public function test_queued_diagnostics_substitute_invalid_utf8_without_stopping(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        self::assertSame(0, $this->server($output)->serve($input, $output));
        rewind($output);
        $notification = new MessageReader()->read($output);
        self::assertSame("bad\u{FFFD}", $notification['params']['diagnostics'][0]['message']);
        fclose($input);
        fclose($output);
    }

    public function test_queued_diagnostics_with_closed_output_exit_cleanly(): void
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        $server = $this->server($output);
        fclose($output);
        self::assertSame(1, $server->serve($input, $output));
        fclose($input);
    }

    private function server($output): LspServer
    {
        $api = $this->createStub(ApiFacadeInterface::class);
        $api->method('analyzeSource')->willReturn([new Diagnostic('test/code', Diagnostic::SEVERITY_ERROR, "bad\xE9", 'file:///project/queued.phel', 1, 1, 1, 2)]);
        $publisher = new DiagnosticPublisher($api, new LintFacade(), new DiagnosticConverter(new PositionConverter(), new UriConverter()), 0, static fn(): float => 0.0);
        $responses = new ResponseBuilder();
        $writer = new MessageWriter();
        $sink = new StreamNotificationSink($writer, $responses, $output);
        $publisher->schedule(new Document('file:///project/queued.phel', 'phel', 2, '(ns queued)'), $sink);

        return new LspServer(new MessageReader(), $writer, new RequestDispatcher($responses), $responses, new Session(new DocumentStore(), $sink), $publisher);
    }
}
