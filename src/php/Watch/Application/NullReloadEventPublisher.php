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
    /**
     * Intentionally discards the batch; the empty body is the whole point of
     * this null object. See the class docblock for when to swap it out.
     */
    public function publish(array $events, array $reloadedNamespaces): void {}
}
