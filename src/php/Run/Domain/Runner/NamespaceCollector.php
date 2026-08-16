<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Runner;

use Phel\Run\Domain\Test\CannotFindAnyTestsException;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\NamespaceInformation;

use function array_unique;
use function array_values;
use function realpath;
use function str_starts_with;

/**
 * @internal
 */
final readonly class NamespaceCollector
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
    ) {}

    /**
     * @param list<string> $paths
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesFromPaths(array $paths): array
    {
        $allDirs = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getTestDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        // One scan covers user test ns discovery, bundled phel.* discovery,
        // and the dependency walk that follows. Per-call directory caches in
        // the namespace extractor turn the three downstream consumers into a
        // single filesystem traversal.
        $allInfos = $this->buildFacade->getNamespaceFromDirectories($allDirs);

        $pathInfos = $this->resolvePathInfos($paths);
        $userNamespaces = $this->resolveUserNamespaces($pathInfos, $allInfos);
        if ($userNamespaces === []) {
            throw CannotFindAnyTestsException::inPaths($paths);
        }

        $bundledRoots = $this->resolveRoots([
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ]);
        $bundled = [];
        foreach ($allInfos as $info) {
            $ns = $info->getNamespace();
            if (!str_starts_with($ns, 'phel.')) {
                continue;
            }

            if (!$this->isFileUnderAny($info->getFile(), $bundledRoots)) {
                continue;
            }

            $bundled[$ns] = true;
        }

        // An explicit path may live outside every configured directory. The
        // walk indexes only those directories, so it can neither find such a
        // file nor follow what it requires: seeding its namespace alone
        // dequeued a name with no index entry and dropped its dependencies on
        // the floor. Seeding the dependencies it declares makes the walk load
        // them in order, and the file itself is appended after them (#3187).
        $seeds = array_values(array_unique([
            ...$userNamespaces,
            ...$this->declaredDependencies($pathInfos),
            ...array_keys($bundled),
        ]));

        $dependencies = $this->buildFacade->getDependenciesForNamespace($allDirs, $seeds);

        return $this->appendUnresolvedPathInfos($pathInfos, $dependencies);
    }

    /**
     * The namespace information of every explicit path, read once and shared
     * by the seeding, the dependency seeding and the final append.
     *
     * @param list<string> $paths
     *
     * @return list<NamespaceInformation>
     */
    private function resolvePathInfos(array $paths): array
    {
        return array_map(
            $this->buildFacade->getNamespaceFromFile(...),
            $paths,
        );
    }

    /**
     * What the explicit paths `(:require ...)`, so the walk resolves and
     * orders them even when the requiring file is not under any configured
     * directory. Bundled `phel.*` requires resolve through the same walk.
     *
     * @param list<NamespaceInformation> $pathInfos
     *
     * @return list<string>
     */
    private function declaredDependencies(array $pathInfos): array
    {
        $dependencies = [];
        foreach ($pathInfos as $info) {
            foreach ($info->getDependencies() as $dependency) {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * Explicit paths may live outside every configured directory. The
     * dependency walk can only map namespaces back to files inside those
     * directories, so such files would be silently dropped from the run.
     * Append their own namespace information after everything they depend
     * on, which the walk has already placed via the seeds.
     *
     * @param list<NamespaceInformation> $pathInfos
     * @param list<NamespaceInformation> $dependencies
     *
     * @return list<NamespaceInformation>
     */
    private function appendUnresolvedPathInfos(array $pathInfos, array $dependencies): array
    {
        $resolved = [];
        foreach ($dependencies as $info) {
            $resolved[$info->getNamespace()] = true;
        }

        foreach ($pathInfos as $info) {
            if (isset($resolved[$info->getNamespace()])) {
                continue;
            }

            $dependencies[] = $info;
            $resolved[$info->getNamespace()] = true;
        }

        return $dependencies;
    }

    /**
     * @param list<NamespaceInformation> $pathInfos
     * @param list<NamespaceInformation> $allInfos
     *
     * @return list<string>
     */
    private function resolveUserNamespaces(array $pathInfos, array $allInfos): array
    {
        if ($pathInfos !== []) {
            return array_map(
                static fn(NamespaceInformation $info): string => $info->getNamespace(),
                $pathInfos,
            );
        }

        $testRoots = $this->resolveRoots($this->commandFacade->getTestDirectories());

        $namespaces = [];
        foreach ($allInfos as $info) {
            if ($this->isFileUnderAny($info->getFile(), $testRoots)) {
                $namespaces[$info->getNamespace()] = true;
            }
        }

        return array_keys($namespaces);
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function resolveRoots(array $directories): array
    {
        $roots = [];
        foreach ($directories as $dir) {
            $real = realpath($dir);
            $roots[] = $real !== false ? $real : $dir;
        }

        return $roots;
    }

    /**
     * @param list<string> $roots
     */
    private function isFileUnderAny(string $file, array $roots): bool
    {
        return array_any($roots, static fn(string $root): bool => $file === $root || str_starts_with($file, $root . '/'));
    }
}
