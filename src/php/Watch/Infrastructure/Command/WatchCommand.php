<?php

declare(strict_types=1);

namespace Phel\Watch\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use InvalidArgumentException;
use Phel;
use Phel\Shared\ExistingPaths;
use Phel\Shared\InvocationError;
use Phel\Shared\NumericOption;
use Phel\Shared\ScalarCoercion;
use Phel\Watch\Application\Watcher\FileWatcherBuilder;
use Phel\Watch\WatchConfig;
use Phel\Watch\WatchFacade;
use Phel\Watch\WatchFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function implode;
use function in_array;
use function sprintf;
use function strtolower;

/**
 * `./bin/phel watch [paths]...` — watch `.phel` files and reload them on
 * change. Uses inotify on Linux, fswatch on macOS, polling on Windows.
 *
 * @method WatchFacade  getFacade()
 * @method WatchFactory getFactory()
 * @method WatchConfig  getConfig()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: WatchFacade::class)]
#[ServiceMap(method: 'getFactory', className: WatchFactory::class)]
#[ServiceMap(method: 'getConfig', className: WatchConfig::class)]
final class WatchCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string COMMAND_NAME = 'watch';

    private const string ARG_PATHS = 'paths';

    private const string OPT_BACKEND = 'backend';

    private const string OPT_POLL = 'poll';

    private const string OPT_DEBOUNCE = 'debounce';

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Watch Phel files and reload namespaces on change.')
            ->setHelp(<<<'HELP'
Watches source dirs and re-evaluates changed namespaces in dependency order.

<info>Examples:</info>
  <comment>phel watch</comment>                Watch the configured source dirs
  <comment>phel watch src -b polling</comment>   Watch a dir with the polling backend
HELP)
            ->addArgument(
                self::ARG_PATHS,
                InputArgument::IS_ARRAY,
                'Files or directories to watch (defaults to the configured source dirs).',
                [],
            )
            ->addOption(
                self::OPT_BACKEND,
                'b',
                InputOption::VALUE_REQUIRED,
                'Watcher backend: auto, inotify, fswatch, polling.',
                WatchConfig::defaultBackend(),
            )
            ->addOption(
                self::OPT_POLL,
                null,
                InputOption::VALUE_REQUIRED,
                'Polling interval in milliseconds (polling backend only).',
                (string) WatchConfig::defaultPollIntervalMs(),
            )
            ->addOption(
                self::OPT_DEBOUNCE,
                null,
                InputOption::VALUE_REQUIRED,
                'Debounce window in milliseconds.',
                (string) WatchConfig::defaultDebounceMs(),
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = (array) $input->getArgument(self::ARG_PATHS);
        if (!ExistingPaths::reportMissing($paths, $output)) {
            return self::INVALID;
        }

        if ($paths === []) {
            $paths = ExistingPaths::filter($this->defaultPaths());
        }

        if ($paths === []) {
            return InvocationError::report($output, 'No readable paths to watch: none of the configured source directories exists.');
        }

        $backend = strtolower(ScalarCoercion::toString($input->getOption(self::OPT_BACKEND)));
        $knownBackends = [WatchConfig::defaultBackend(), ...FileWatcherBuilder::BACKENDS];
        if (!in_array($backend, $knownBackends, true)) {
            return InvocationError::report($output, sprintf('Unknown backend: %s. Known: %s.', $backend, implode(', ', $knownBackends)));
        }

        $backend = $backend === WatchConfig::defaultBackend() ? null : $backend;

        try {
            $poll = (int) NumericOption::wholeNumber($input, self::OPT_POLL, 1);
            $debounce = (int) NumericOption::wholeNumber($input, self::OPT_DEBOUNCE, 0);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return InvocationError::report($output, $invalidArgumentException->getMessage());
        }

        Phel::setupRuntimeArgs('watch', []);
        $this->getFactory()->getRunFacade()->loadPhelNamespaces();

        $watcher = $this->getFactory()->createFileWatcher($backend, $poll, $debounce);
        $output->writeln(sprintf(
            '<info>Watching %s via %s backend (poll %dms, debounce %dms). Press Ctrl+C to stop.</info>',
            implode(', ', $paths),
            $watcher->name(),
            $poll,
            $debounce,
        ));

        try {
            $this->getFacade()->watch($paths, [
                'backend' => $backend,
                'poll' => $poll,
                'debounce' => $debounce,
            ]);
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function defaultPaths(): array
    {
        $cmd = $this->getFactory()->getCommandFacade();

        return $cmd->getSourceDirectories();
    }
}
