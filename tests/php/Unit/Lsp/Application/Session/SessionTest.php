<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Session;

use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function test_initial_state_is_not_initialized_nor_shutting_down(): void
    {
        $session = $this->newSession();

        self::assertFalse($session->isInitialized());
        self::assertFalse($session->shutdownRequested());
        self::assertSame([], $session->workspaceRoots());
        self::assertSame([], $session->clientCapabilities());
        self::assertNull($session->projectIndex());
    }

    public function test_mark_initialized_is_sticky(): void
    {
        $session = $this->newSession();

        $session->markInitialized();
        self::assertTrue($session->isInitialized());

        $session->markInitialized();
        self::assertTrue($session->isInitialized());
    }

    public function test_request_shutdown_is_sticky(): void
    {
        $session = $this->newSession();

        $session->requestShutdown();
        self::assertTrue($session->shutdownRequested());

        $session->requestShutdown();
        self::assertTrue($session->shutdownRequested());
    }

    public function test_workspace_roots_round_trip(): void
    {
        $session = $this->newSession();

        $session->setWorkspaceRoots(['/a', '/b']);

        self::assertSame(['/a', '/b'], $session->workspaceRoots());
    }

    public function test_client_capabilities_round_trip(): void
    {
        $session = $this->newSession();
        $caps = ['textDocument' => ['synchronization' => ['didSave' => true]]];

        $session->setClientCapabilities($caps);

        self::assertSame($caps, $session->clientCapabilities());
    }

    public function test_project_index_round_trip(): void
    {
        $session = $this->newSession();
        $index = new ProjectIndex([], []);

        $session->setProjectIndex($index);

        self::assertSame($index, $session->projectIndex());
    }

    public function test_documents_and_sink_are_exposed_unchanged(): void
    {
        $store = new DocumentStore();
        $sink = new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        };

        $session = new Session($store, $sink);

        self::assertSame($store, $session->documents());
        self::assertSame($sink, $session->sink());
    }

    private function newSession(): Session
    {
        return new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
    }
}
