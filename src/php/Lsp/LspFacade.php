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
     * Build an LSP server around the given streams. The caller owns the loop
     * via {@see LspServer::serve()}.
     *
     * @param resource $input
     * @param resource $output
     */
    public function createServer($input, $output): LspServer
    {
        return $this->getFactory()->createServer($input, $output);
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
