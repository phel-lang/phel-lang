<?php

declare(strict_types=1);

namespace Phel\Nrepl;

use Gacela\Framework\AbstractFacade;
use Phel\Nrepl\Infrastructure\NreplSocketServer;

/**
 * @extends AbstractFacade<NreplFactory>
 */
final class NreplFacade extends AbstractFacade
{
    /**
     * @param (callable(string): void)|null $logger receives one already-formatted log line
     */
    public function createSocketServer(
        int $port,
        string $host,
        ?callable $logger = null,
    ): NreplSocketServer {
        return $this->getFactory()->createSocketServer($port, $host, $logger);
    }

    public function loadPhelNamespaces(): void
    {
        $this->getFactory()
            ->getRunFacade()
            ->loadPhelNamespaces();
    }
}
