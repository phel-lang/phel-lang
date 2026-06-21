<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel;
use Phel\Lang\LoadClasspath;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

use function dirname;
use function file_exists;
use function getcwd;

final class NamespaceLoader
{
    private static bool $dataReadersLoaded = false;

    public function __construct(
        private readonly BuildFacadeInterface $buildFacade,
        private readonly CommandFacadeInterface $commandFacade,
        private readonly CompilerFacadeInterface $compilerFacade,
        private readonly BundledNamespaces $bundledNamespaces,
        private readonly NamespaceFileTracker $fileTracker,
        private readonly string $defaultReplStartupFile,
    ) {}

    public static function reset(): void
    {
        NamespaceFileTracker::reset();
        self::$dataReadersLoaded = false;
    }

    public function loadPhelNamespaces(?string $replStartupFile = null): void
    {
        if ($replStartupFile === null) {
            $replStartupFile = $this->defaultReplStartupFile;
        }

        if (!file_exists($replStartupFile)) {
            return;
        }

        $namespace = $this->buildFacade
            ->getNamespaceFromFile($replStartupFile)
            ->getNamespace();

        $srcDirectories = $this->buildSrcDirectories($replStartupFile);

        // Publish the classpath before evaluating any file so `(load ...)`
        // forms inside core.phel (or any other namespace) can resolve
        // classpath-relative paths against the search roots.
        LoadClasspath::publish($srcDirectories);

        $this->evaluateAll($this->resolveSeeds($namespace), $srcDirectories);
        $this->restoreStartupNamespace($namespace);

        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, '*file*', '');

        $this->loadDataReaders($srcDirectories);
        $this->registerLazyBundledNamespaceResolver($srcDirectories);
    }

    /**
     * Bundled modules each switch the global namespace as they evaluate;
     * restore the startup namespace so the REPL/eval session lands in the
     * expected scope (matching the pre-bundled-seeding behavior).
     */
    private function restoreStartupNamespace(string $namespace): void
    {
        $this->compilerFacade->getGlobalEnvironment()->setNs($namespace);
        Phel::setVar(CompilerConstants::PHEL_CORE_NAMESPACE, '*ns*', $namespace);
    }

    /**
     * Seed dependency resolution with the startup namespace and `phel.core`
     * only. The other bundled `phel.*` modules (`phel.html`, `phel.json`,
     * `phel.test`, ...) load lazily on first fully qualified reference or
     * explicit `(require ...)`, so time-to-prompt drops to roughly the
     * `phel.core`-only load cost. The lazy loader is wired in
     * {@see registerLazyBundledNamespaceResolver()}.
     *
     * @return list<string>
     */
    private function resolveSeeds(string $startupNamespace): array
    {
        if ($startupNamespace === CompilerConstants::PHEL_CORE_NAMESPACE) {
            return [CompilerConstants::PHEL_CORE_NAMESPACE];
        }

        return [
            $startupNamespace,
            CompilerConstants::PHEL_CORE_NAMESPACE,
        ];
    }

    /**
     * @param list<string> $seeds
     * @param list<string> $srcDirectories
     */
    private function evaluateAll(array $seeds, array $srcDirectories): void
    {
        foreach ($this->buildFacade->getDependenciesForNamespace($srcDirectories, $seeds) as $info) {
            $file = $info->getFile();
            if (!$this->fileTracker->isLoaded($file)) {
                $this->buildFacade->evalFile($file);
                $this->fileTracker->markLoaded($file);
            }
        }
    }

    /**
     * Registers the on-demand resolver so a fully qualified reference to a
     * bundled namespace that was not seeded eagerly (`phel.json/encode`) loads
     * that namespace the first time the analyzer meets it, instead of failing
     * with "not defined".
     *
     * @param list<string> $srcDirectories
     */
    private function registerLazyBundledNamespaceResolver(array $srcDirectories): void
    {
        $this->compilerFacade->getGlobalEnvironment()->setBundledNamespaceResolver(
            new LazyBundledNamespaceResolver(
                $this->buildFacade,
                $this->bundledNamespaces->all(),
                $srcDirectories,
                $this->fileTracker,
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function buildSrcDirectories(string $replStartupFile): array
    {
        $srcDirectories = [
            dirname($replStartupFile),
            ...$this->commandFacade->getAllPhelDirectories(),
        ];

        $cwd = getcwd();
        if ($cwd !== false) {
            $srcDirectories[] = $cwd;
        }

        return $srcDirectories;
    }

    /**
     * @param list<string> $srcDirectories
     */
    private function loadDataReaders(array $srcDirectories): void
    {
        if (self::$dataReadersLoaded) {
            return;
        }

        self::$dataReadersLoaded = true;
        new DataReadersLoader($this->buildFacade)->load($srcDirectories);
    }
}
