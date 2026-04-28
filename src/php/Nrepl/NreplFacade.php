<?php

declare(strict_types=1);

namespace Phel\Nrepl;

use Gacela\Framework\AbstractFacade;
use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Infrastructure\NreplSocketServer;

/**
 * @extends AbstractFacade<NreplFactory>
 */
final class NreplFacade extends AbstractFacade
{
    public function createSocketServer(
        int $port,
        string $host,
        ?callable $logger = null,
    ): NreplSocketServer {
        return $this->getFactory()->createSocketServer($port, $host, $logger);
    }

    public function createOpDispatcher(): OpDispatcher
    {
        return $this->getFactory()->createOpDispatcher();
    }

    public function loadPhelNamespaces(): void
    {
        $this->getFactory()
            ->getRunFacade()
            ->loadPhelNamespaces();
    }
}
