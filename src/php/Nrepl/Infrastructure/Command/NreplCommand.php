<?php

declare(strict_types=1);

namespace Phel\Nrepl\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel;
use Phel\Nrepl\NreplConfig;
use Phel\Nrepl\NreplFacade;
use Phel\Nrepl\NreplFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

#[ServiceMap(method: 'getFacade', className: NreplFacade::class)]
#[ServiceMap(method: 'getFactory', className: NreplFactory::class)]
#[ServiceMap(method: 'getConfig', className: NreplConfig::class)]
final class NreplCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string COMMAND_NAME = 'nrepl';

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Start an nREPL server for editor tooling (bencode-over-TCP protocol).')
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'TCP port to listen on (default 7888). Use 0 to bind a random free port.',
                (string) NreplConfig::defaultPort(),
            )
            ->addOption(
                'host',
                null,
                InputOption::VALUE_REQUIRED,
                'Host/address to bind (default 127.0.0.1).',
                NreplConfig::defaultHost(),
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = (int) $input->getOption('port');
        $host = (string) $input->getOption('host');

        // Normalise runtime args so loaded code sees a clean argv.
        Phel::setupRuntimeArgs('nrepl', []);
        $this->getFacade()->loadPhelNamespaces();

        try {
            $server = $this->getFactory()->createSocketServer(
                $port,
                $host,
                static function (string $line) use ($output): void {
                    $output->writeln($line);
                },
            );
            $server->start();
            $output->writeln(sprintf('nREPL server started on %s:%d', $host, $server->port()));
            $output->writeln('Connect your editor via the bencode-over-TCP nREPL protocol.');
            $server->run();

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
            return self::FAILURE;
        }
    }
}
