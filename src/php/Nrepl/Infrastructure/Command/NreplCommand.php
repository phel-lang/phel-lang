<?php

declare(strict_types=1);

namespace Phel\Nrepl\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel;
use Phel\Nrepl\Infrastructure\NreplSocketServer;
use Phel\Nrepl\NreplConfig;
use Phel\Nrepl\NreplFacade;
use Phel\Nrepl\NreplFactory;
use Phel\Shared\CompilerConstants;
use Phel\Shared\ReplConstants;
use Phel\Shared\ScalarCoercion;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function function_exists;
use function getcwd;
use function pcntl_async_signals;
use function pcntl_signal;
use function register_shutdown_function;
use function sprintf;

use const SIGINT;
use const SIGTERM;

/**
 * @method NreplFacade  getFacade()
 * @method NreplFactory getFactory()
 * @method NreplConfig  getConfig()
 *
 * @internal
 */
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
            ->setHelp(<<<'HELP'
Starts an nREPL server your editor (Cursive, Calva, CIDER, Conjure) connects to.
The bound port is written to <comment>.nrepl-port</comment> in the current directory, and the
file is removed again when the server stops.

<info>Examples:</info>
  <comment>phel nrepl</comment>             Listen on the default 127.0.0.1:7888
  <comment>phel nrepl --port=0</comment>    Bind a random free port
HELP)
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
        $port = ScalarCoercion::toInt($input->getOption('port'));
        $host = ScalarCoercion::toString($input->getOption('host'));

        // Normalise runtime args so loaded code sees a clean argv.
        Phel::setupRuntimeArgs('nrepl', []);
        $this->getFacade()->loadPhelNamespaces();

        // An editor session re-evaluates the same form as it is edited, so the
        // duplicate-definition guard has to stand down here as it does at the
        // `phel repl` prompt (#2896).
        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::INTERACTIVE_MODE, true);

        try {
            $server = $this->getFactory()->createSocketServer(
                $port,
                $host,
                static function (string $line) use ($output): void {
                    $output->writeln($line);
                },
            );
            $server->start();

            // Register cleanup before writing the file, so a signal landing
            // at any point afterwards takes the graceful path.
            // getcwd() fails only when the working directory has been removed
            // from under the process; `.` still names it for file_put_contents.
            $cwd = getcwd();
            $portFile = $this->getFactory()->createPortFile($cwd === false ? '.' : $cwd);
            // Backstop if the process exits while the accept loop is still
            // running (e.g. on a fatal error); the finally below covers the
            // graceful path.
            register_shutdown_function(static function () use ($portFile): void {
                $portFile->delete();
            });
            $this->registerSignalHandlers($server);

            $output->writeln(sprintf('nREPL server started on %s:%d', $host, $server->port()));

            // A directory we cannot write to costs editors their automatic
            // discovery, nothing more: the server is still usable through an
            // explicit --port, so say so and keep listening.
            try {
                $portFile->write($server->port());
                $output->writeln(sprintf('Port written to %s', $portFile->path()));
            } catch (RuntimeException $runtimeException) {
                $output->writeln(sprintf(
                    '<comment>%s Connect on port %d explicitly.</comment>',
                    $runtimeException->getMessage(),
                    $server->port(),
                ));
            }

            $output->writeln('Connect your editor via the bencode-over-TCP nREPL protocol.');

            try {
                $server->run();
            } finally {
                $server->stop();
                $portFile->delete();
            }

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
            return self::FAILURE;
        }
    }

    /**
     * Stop the accept loop on Ctrl+C/SIGTERM so run() returns and the
     * finally block removes the port file, instead of the OS killing the
     * process and leaving the file behind. No-op without ext-pcntl.
     */
    private function registerSignalHandlers(NreplSocketServer $server): void
    {
        if (!function_exists('pcntl_signal') || !function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        $handler = static function () use ($server): void {
            $server->stop();
        };
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }
}
