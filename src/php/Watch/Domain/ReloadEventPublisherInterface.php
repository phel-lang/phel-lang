<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

use Phel\Watch\Transfer\WatchEvent;

/**
 * Fan-out hook for reload events. The default implementation is a no-op; a
 * future nREPL integration publishes a `reload` event to every connected
 * session.
 */
interface ReloadEventPublisherInterface
{
    /**
     * @param list<WatchEvent> $events
     * @param list<string>     $reloadedNamespaces
     */
    public function publish(array $events, array $reloadedNamespaces): void;
}
