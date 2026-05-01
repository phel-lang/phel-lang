<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadClasspath;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

use function array_unique;
use function array_values;
use function dirname;
use function file_exists;
use function getcwd;

final class NamespaceLoader
{
    private static array $loadedFiles = [];

    private static bool $dataReadersLoaded = false;

    public function __construct(
        private readonly BuildFacadeInterface $buildFacade,
        private readonly CommandFacadeInterface $commandFacade,
        private readonly BundledNamespaces $bundledNamespaces,
        private readonly string $defaultReplStartupFile,
    ) {}

    public static function reset(): void
    {
        self::$loadedFiles = [];
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

        // Seed dependency resolution with both the startup namespace and every
        // bundled `phel.*` module so fully qualified references like
        // `phel.async/delay` or `phel.json/encode` work without forcing user
        // code to spell out a `(:require ...)` for each one.
        $seeds = [$namespace, CompilerConstants::PHEL_CORE_NAMESPACE];
        foreach ($this->bundledNamespaces->all() as $bundled) {
            $seeds[] = $bundled;
        }

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            $srcDirectories,
            array_values(array_unique($seeds)),
        );

        foreach ($namespaceInformation as $info) {
            $file = $info->getFile();
            if (!isset(self::$loadedFiles[$file])) {
                $this->buildFacade->evalFile($file);
                self::$loadedFiles[$file] = true;
            }
        }

        // Bundled modules each switch the global namespace as they evaluate;
        // restore the startup namespace so the REPL/eval session lands in the
        // expected scope (matching the pre-bundled-seeding behavior).
        GlobalEnvironmentSingleton::getInstance()->setNs($namespace);

        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, '*file*', '');

        $this->loadDataReaders($srcDirectories);
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
