<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Handler;

use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\DidCloseHandler;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\TestCase;

final class DidCloseHandlerTest extends TestCase
{
    public function test_is_a_notification_for_did_close_method(): void
    {
        $handler = new DidCloseHandler(new ParamsExtractor());

        self::assertSame('textDocument/didClose', $handler->method());
        self::assertTrue($handler->isNotification());
    }

    public function test_noops_when_params_are_empty(): void
    {
        $captured = [];
        $session = $this->newSession($captured);

        $handler = new DidCloseHandler(new ParamsExtractor());
        $result = $handler->handle([], $session);

        self::assertNull($result);
        self::assertSame([], $captured);
    }

    public function test_noops_when_text_document_is_not_an_array(): void
    {
        $captured = [];
        $session = $this->newSession($captured);

        $handler = new DidCloseHandler(new ParamsExtractor());
        $handler->handle(['textDocument' => 'garbage'], $session);

        self::assertSame([], $captured);
    }

    public function test_closes_document_and_clears_diagnostics_when_uri_present(): void
    {
        $captured = [];
        $session = $this->newSession($captured);
        $session->documents()->open('file:///x.phel', 'phel', 1, '(ns x)');

        $handler = new DidCloseHandler(new ParamsExtractor());
        $handler->handle(['textDocument' => ['uri' => 'file:///x.phel']], $session);

        self::assertNull($session->documents()->get('file:///x.phel'));
        self::assertCount(1, $captured);
        self::assertSame('textDocument/publishDiagnostics', $captured[0]['method']);
        self::assertSame('file:///x.phel', $captured[0]['params']['uri']);
        self::assertSame([], $captured[0]['params']['diagnostics']);
    }

    /**
     * @param array<int, array{method: string, params: array<string, mixed>}> $captured
     */
    private function newSession(array &$captured): Session
    {
        $sink = new class($captured) implements NotificationSink {
            /**
             * @param array<int, array{method: string, params: array<string, mixed>}> $captured
             */
            public function __construct(private array &$captured) {}

            public function notify(string $method, array $params): void
            {
                $this->captured[] = ['method' => $method, 'params' => $params];
            }
        };

        return new Session(new DocumentStore(), $sink);
    }
}
