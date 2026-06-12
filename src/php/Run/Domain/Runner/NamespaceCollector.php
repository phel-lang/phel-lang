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

        $userNamespaces = $this->resolveUserNamespaces($paths, $allInfos);
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

        $seeds = array_values(array_unique([
            ...$userNamespaces,
            ...array_keys($bundled),
        ]));

        $dependencies = $this->buildFacade->getDependenciesForNamespace($allDirs, $seeds);

        return $this->appendUnresolvedPathInfos($paths, $dependencies);
    }

    /**
     * Explicit paths may live outside every configured directory. The
     * dependency walk can only map namespaces back to files inside those
     * directories, so such files would be silently dropped from the run.
     * Append their own namespace information instead; their bundled phel.*
     * dependencies are already part of the walk result via the seeds.
     *
     * @param list<string>               $paths
     * @param list<NamespaceInformation> $dependencies
     *
     * @return list<NamespaceInformation>
     */
    private function appendUnresolvedPathInfos(array $paths, array $dependencies): array
    {
        $resolved = [];
        foreach ($dependencies as $info) {
            $resolved[$info->getNamespace()] = true;
        }

        foreach ($paths as $path) {
            $info = $this->buildFacade->getNamespaceFromFile($path);
            if (isset($resolved[$info->getNamespace()])) {
                continue;
            }

            $dependencies[] = $info;
            $resolved[$info->getNamespace()] = true;
        }

        return $dependencies;
    }

    /**
     * @param list<string>               $paths
     * @param list<NamespaceInformation> $allInfos
     *
     * @return list<string>
     */
    private function resolveUserNamespaces(array $paths, array $allInfos): array
    {
        if ($paths !== []) {
            return array_map(
                fn(string $filename): string => $this
                    ->buildFacade
                    ->getNamespaceFromFile($filename)
                    ->getNamespace(),
                $paths,
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
        return array_any($roots, static fn($root): bool => $file === $root || str_starts_with($file, $root . '/'));
    }
}
