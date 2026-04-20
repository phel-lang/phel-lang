<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Handler;

use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\ExitHandler;
use Phel\Lsp\Application\Handler\InitializedHandler;
use Phel\Lsp\Application\Handler\ShutdownHandler;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\TestCase;

final class ShutdownAndExitHandlerTest extends TestCase
{
    public function test_shutdown_sets_flag_and_is_request(): void
    {
        $session = $this->newSession();
        $handler = new ShutdownHandler();

        self::assertSame('shutdown', $handler->method());
        self::assertFalse($handler->isNotification());
        self::assertFalse($session->shutdownRequested());

        $result = $handler->handle([], $session);

        self::assertNull($result);
        self::assertTrue($session->shutdownRequested());
    }

    public function test_exit_sets_flag_and_is_notification(): void
    {
        $session = $this->newSession();
        $handler = new ExitHandler();

        self::assertSame('exit', $handler->method());
        self::assertTrue($handler->isNotification());

        $handler->handle([], $session);

        self::assertTrue($session->shutdownRequested());
    }

    public function test_initialized_marks_session_initialized(): void
    {
        $session = $this->newSession();
        $handler = new InitializedHandler();

        self::assertSame('initialized', $handler->method());
        self::assertTrue($handler->isNotification());
        self::assertFalse($session->isInitialized());

        $handler->handle([], $session);

        self::assertTrue($session->isInitialized());
    }

    private function newSession(): Session
    {
        return new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
    }
}
