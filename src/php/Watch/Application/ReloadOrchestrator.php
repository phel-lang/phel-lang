<?php

declare(strict_types=1);

namespace Phel\Watch\Application;

use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Watch\Domain\NamespaceResolverInterface;
use Phel\Watch\Domain\ProjectReindexerInterface;
use Phel\Watch\Domain\ReloadEventPublisherInterface;
use Phel\Watch\Domain\ReloadOrchestratorInterface;
use Phel\Watch\Transfer\WatchEvent;
use Throwable;

use function sprintf;

/**
 * Given a batch of file-change events, figures out the affected namespaces,
 * reloads them in dependency order, re-runs the `:on-reload` tests in each,
 * publishes a reload event, and re-indexes the project for tooling.
 */
final readonly class ReloadOrchestrator implements ReloadOrchestratorInterface
{
    public function __construct(
        private NamespaceResolverInterface $namespaceResolver,
        private RunFacadeInterface $runFacade,
        private BuildFacadeInterface $buildFacade,
        private ProjectReindexerInterface $reindexer,
        private ReloadEventPublisherInterface $publisher,
    ) {}

    /**
     * @param list<WatchEvent> $events
     * @param list<string>     $srcDirs
     *
     * @return list<string>
     */
    public function handleChanges(array $events, array $srcDirs): array
    {
        if ($events === []) {
            return [];
        }

        $namespaces = $this->resolveNamespaces($events);
        if ($namespaces === []) {
            $this->publisher->publish($events, []);
            return [];
        }

        $reloaded = $this->reloadNamespaces($namespaces, $srcDirs);
        $this->runReloadHooks($reloaded);
        $this->reindex($srcDirs);
        $this->publisher->publish($events, $reloaded);

        return $reloaded;
    }

    /**
     * @param list<WatchEvent> $events
     *
     * @return list<string>
     */
    private function resolveNamespaces(array $events): array
    {
        /** @var array<string, bool> $seen */
        $seen = [];
        $namespaces = [];

        foreach ($events as $event) {
            if ($event->kind === WatchEvent::KIND_DELETED) {
                continue;
            }

            $ns = $this->namespaceResolver->resolveFromFile($event->path);
            if ($ns === null) {
                continue;
            }

            if (isset($seen[$ns])) {
                continue;
            }

            $seen[$ns] = true;
            $namespaces[] = $ns;
        }

        return $namespaces;
    }

    /**
     * @param list<string> $namespaces
     * @param list<string> $srcDirs
     *
     * @return list<string>
     */
    private function reloadNamespaces(array $namespaces, array $srcDirs): array
    {
        try {
            $deps = $this->buildFacade->getDependenciesForNamespace($srcDirs, $namespaces);
        } catch (Throwable) {
            return [];
        }

        $reloaded = [];
        foreach ($deps as $info) {
            try {
                $this->runFacade->evalFile($info);
            } catch (Throwable) {
                // Keep going so one broken file doesn't prevent the rest of
                // the chain from reloading.
                continue;
            }

            $reloaded[] = $info->getNamespace();
        }

        return $reloaded;
    }

    /**
     * @param list<string> $reloadedNamespaces
     */
    private function runReloadHooks(array $reloadedNamespaces): void
    {
        foreach ($reloadedNamespaces as $namespace) {
            $code = sprintf('(phel\watch/run-on-reload-hooks "%s")', $namespace);
            try {
                $this->runFacade->structuredEval($code, new CompileOptions());
            } catch (Throwable) {
                // Hooks are best-effort. Do not fail the watch loop.
            }
        }
    }

    /**
     * @param list<string> $srcDirs
     */
    private function reindex(array $srcDirs): void
    {
        $this->reindexer->reindex($srcDirs);
    }
}
