<?php

declare(strict_types=1);

namespace Phel\Watch\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel;
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
use function is_dir;
use function is_file;
use function sprintf;

/**
 * `./bin/phel watch [paths]...` — watch `.phel` files and reload them on
 * change. Uses inotify on Linux, fswatch on macOS, polling on Windows.
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
        if ($paths === []) {
            $paths = $this->defaultPaths();
        }

        $paths = $this->filterExistingPaths($paths);
        if ($paths === []) {
            $output->writeln('<error>No readable paths to watch.</error>');
            return self::FAILURE;
        }

        $backend = (string) $input->getOption(self::OPT_BACKEND);
        $backend = $backend === WatchConfig::defaultBackend() ? null : $backend;

        $poll = (int) $input->getOption(self::OPT_POLL);
        $debounce = (int) $input->getOption(self::OPT_DEBOUNCE);

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

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function filterExistingPaths(array $paths): array
    {
        $filtered = [];
        foreach ($paths as $path) {
            if (is_file($path) || is_dir($path)) {
                $filtered[] = $path;
            }
        }

        return $filtered;
    }
}
