<?php

declare(strict_types=1);

namespace Phel\Watch\Application;

use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Watch\Application\Watcher\FileWatcherBuilder;
use Phel\Watch\Domain\ReloadOrchestratorInterface;
use Phel\Watch\Transfer\WatchEvent;

use function array_unique;
use function array_values;
use function is_dir;
use function is_file;

/**
 * Orchestrates one full watch session: picks a backend, resolves source
 * directories, wires the reload orchestrator, and blocks in the watcher
 * loop. Lives in the Application layer so `WatchFacade::watch()` stays a
 * single-delegation.
 */
final readonly class WatchRunner
{
    public function __construct(
        private FileWatcherBuilder $builder,
        private ReloadOrchestratorInterface $orchestrator,
        private CommandFacadeInterface $commandFacade,
    ) {}

    /**
     * @param list<string>                                      $paths
     * @param array{backend?:?string,poll?:?int,debounce?:?int} $options
     */
    public function run(array $paths, array $options = []): void
    {
        $paths = $this->filterExistingPaths($paths);
        if ($paths === []) {
            return;
        }

        $watcher = $this->builder->create($options['backend'] ?? null);
        $srcDirs = $this->srcDirs($paths);

        $orchestrator = $this->orchestrator;
        $watcher->watch($paths, static function (array $events) use ($orchestrator, $srcDirs): void {
            /** @var list<WatchEvent> $events */
            $orchestrator->handleChanges($events, $srcDirs);
        });
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

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function srcDirs(array $paths): array
    {
        $dirs = $this->commandFacade->getAllPhelDirectories();
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $dirs[] = $path;
            }
        }

        return array_values(array_unique($dirs));
    }
}
