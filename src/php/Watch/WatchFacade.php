<?php

declare(strict_types=1);

namespace Phel\Watch;

use Gacela\Framework\AbstractFacade;
use Phel\Watch\Application\Watcher\FileWatcherBuilder;
use Phel\Watch\Domain\FileWatcherInterface;
use Phel\Watch\Domain\NamespaceResolverInterface;
use Phel\Watch\Domain\ReloadEventPublisherInterface;
use Phel\Watch\Domain\ReloadOrchestratorInterface;

/**
 * @extends AbstractFacade<WatchFactory>
 */
final class WatchFacade extends AbstractFacade
{
    /**
     * Watch the given paths and trigger hot reloads. Blocks until the watcher
     * is stopped.
     *
     * @param list<string>                                                                                $paths
     * @param array{backend?:?string,poll?:?int,debounce?:?int,publisher?:?ReloadEventPublisherInterface} $options
     */
    public function watch(array $paths, array $options = []): void
    {
        $this->getFactory()
            ->createWatchRunner(
                $options['publisher'] ?? null,
                $options['poll'] ?? null,
                $options['debounce'] ?? null,
            )
            ->run($paths, [
                'backend' => $options['backend'] ?? null,
                'poll' => $options['poll'] ?? null,
                'debounce' => $options['debounce'] ?? null,
            ]);
    }

    public function createFileWatcher(?string $preferred = null, ?int $pollIntervalMs = null, ?int $debounceMs = null): FileWatcherInterface
    {
        return $this->getFactory()->createFileWatcher($preferred, $pollIntervalMs, $debounceMs);
    }

    public function createFileWatcherBuilder(?int $pollIntervalMs = null, ?int $debounceMs = null): FileWatcherBuilder
    {
        return $this->getFactory()->createFileWatcherBuilder($pollIntervalMs, $debounceMs);
    }

    public function createReloadOrchestrator(?ReloadEventPublisherInterface $publisher = null): ReloadOrchestratorInterface
    {
        return $this->getFactory()->createReloadOrchestrator($publisher);
    }

    public function createNamespaceResolver(): NamespaceResolverInterface
    {
        return $this->getFactory()->createNamespaceResolver();
    }
}
