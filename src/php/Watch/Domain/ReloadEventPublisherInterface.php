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
     * Called once per non-empty file-change batch with the originating events
     * and the namespaces that were successfully reloaded. It is still invoked
     * when $reloadedNamespaces is empty (e.g. namespace resolution failed or
     * every reload threw), so implementers must not assume a reload happened.
     *
     * @param list<WatchEvent> $events
     * @param list<string>     $reloadedNamespaces
     */
    public function publish(array $events, array $reloadedNamespaces): void;
}
