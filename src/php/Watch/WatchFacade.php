<?php

declare(strict_types=1);

namespace Phel\Watch;

use Gacela\Framework\AbstractFacade;
use Phel\Watch\Domain\FileWatcherInterface;
use Phel\Watch\Domain\ReloadEventPublisherInterface;

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
}
