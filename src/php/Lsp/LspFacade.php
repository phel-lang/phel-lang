<?php

declare(strict_types=1);

namespace Phel\Lsp;

use Gacela\Framework\AbstractFacade;
use Phel\Lsp\Application\Rpc\LspServer;
use Phel\Lsp\Application\Rpc\RequestDispatcher;

/**
 * @extends AbstractFacade<LspFactory>
 */
final class LspFacade extends AbstractFacade
{
    /**
     * Build an LSP server bound to the given output stream (used for
     * server-initiated notifications). The caller owns the loop and supplies
     * both streams to {@see LspServer::serve()}.
     *
     * @param resource $output
     */
    public function createServer($output): LspServer
    {
        return $this->getFactory()->createServer($output);
    }

    /**
     * Build a request dispatcher with every handler registered. Exposed so
     * unit tests can drive handlers without a real transport.
     */
    public function createDispatcher(): RequestDispatcher
    {
        return $this->getFactory()->createDispatcher();
    }
}
