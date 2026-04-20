<?php

declare(strict_types=1);

namespace Phel\Lsp\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel;
use Phel\Lsp\LspConfig;
use Phel\Lsp\LspFacade;
use Phel\Lsp\LspFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function defined;
use function fopen;
use function sprintf;

#[ServiceMap(method: 'getFacade', className: LspFacade::class)]
#[ServiceMap(method: 'getFactory', className: LspFactory::class)]
#[ServiceMap(method: 'getConfig', className: LspConfig::class)]
final class LspCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string COMMAND_NAME = 'lsp';

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Start the Phel Language Server (LSP v3.17 over stdio, JSON-RPC 2.0 with Content-Length framing).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Phel::setupRuntimeArgs('lsp', []);

        try {
            $this->getFactory()->getRunFacade()->loadPhelNamespaces();
        } catch (Throwable) {
            // Core may fail to load on malformed projects; fall back to an analyzer-only session.
        }

        $stdin = defined('STDIN') ? STDIN : fopen('php://stdin', 'rb');
        $stdout = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'wb');
        if ($stdin === false || $stdout === false) {
            $output->writeln('<error>Cannot open stdio streams for LSP server.</error>');
            return self::FAILURE;
        }

        try {
            return $this->getFacade()->createServer($stdin, $stdout)->serve($stdin, $stdout);
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>LSP server crashed: %s</error>', $throwable->getMessage()));
            return self::FAILURE;
        }
    }
}
