<?php

declare(strict_types=1);

namespace Phel\Watch\Application;

use Phel\Watch\Domain\ReloadEventPublisherInterface;

/**
 * Default publisher: swallows every event. Replaced when the watcher is
 * hosted inside an nREPL process.
 */
final class NullReloadEventPublisher implements ReloadEventPublisherInterface
{
    public function publish(array $events, array $reloadedNamespaces): void {}
}
