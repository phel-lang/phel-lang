<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Lsp\Infrastructure\Command\LspCommand;
use Symfony\Component\Console\Command\LazyCommand;

final class LspCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('lsp', [], 'Start the Phel Language Server (LSP v3.17 over stdio, JSON-RPC 2.0 with Content-Length framing).', false, static fn(): LspCommand => new LspCommand()),
        ];
    }
}
