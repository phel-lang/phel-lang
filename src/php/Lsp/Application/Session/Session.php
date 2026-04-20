<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Session;

use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Domain\NotificationSink;

/**
 * State carried across every LSP request for a single client.
 *
 * Handlers read the workspace roots to re-index the project, push
 * notifications back through the sink, and share the document store.
 */
final class Session
{
    private bool $initialized = false;

    private bool $shutdownRequested = false;

    /** @var list<string> */
    private array $workspaceRoots = [];

    /** @var array<string, mixed> */
    private array $clientCapabilities = [];

    private ?ProjectIndex $projectIndex = null;

    public function __construct(
        private readonly DocumentStore $documents,
        private readonly NotificationSink $sink,
    ) {}

    public function documents(): DocumentStore
    {
        return $this->documents;
    }

    public function sink(): NotificationSink
    {
        return $this->sink;
    }

    public function markInitialized(): void
    {
        $this->initialized = true;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function requestShutdown(): void
    {
        $this->shutdownRequested = true;
    }

    public function shutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }

    /**
     * @param list<string> $roots
     */
    public function setWorkspaceRoots(array $roots): void
    {
        $this->workspaceRoots = $roots;
    }

    /**
     * @return list<string>
     */
    public function workspaceRoots(): array
    {
        return $this->workspaceRoots;
    }

    /**
     * @param array<string, mixed> $capabilities
     */
    public function setClientCapabilities(array $capabilities): void
    {
        $this->clientCapabilities = $capabilities;
    }

    /**
     * @return array<string, mixed>
     */
    public function clientCapabilities(): array
    {
        return $this->clientCapabilities;
    }

    public function setProjectIndex(ProjectIndex $index): void
    {
        $this->projectIndex = $index;
    }

    public function projectIndex(): ?ProjectIndex
    {
        return $this->projectIndex;
    }
}
