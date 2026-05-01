<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Runner;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Application\BundledNamespaces;
use Phel\Run\Domain\Test\CannotFindAnyTestsException;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

use function array_unique;
use function array_values;

final readonly class NamespaceCollector
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
        private BundledNamespaces $bundledNamespaces,
    ) {}

    /**
     * @return list<NamespaceInformation>
     */
    public function getDependenciesFromPaths(array $paths): array
    {
        $namespaces = $this->getNamespacesFromPaths($paths);
        if ($namespaces === []) {
            throw CannotFindAnyTestsException::inPaths($paths);
        }

        // Seed the bundled `phel.*` modules alongside the user test namespaces
        // so test files can reach them via FQN (`phel.async/delay`, ...) without
        // forcing each one to declare a `(:require ...)`.
        $seeds = array_values(array_unique([
            ...$namespaces,
            ...$this->bundledNamespaces->all(),
        ]));

        return $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getTestDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            $seeds,
        );
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function getNamespacesFromPaths(array $paths): array
    {
        if ($paths === []) {
            $namespaces = $this->buildFacade->getNamespaceFromDirectories(
                $this->commandFacade->getTestDirectories(),
            );

            return array_map(
                static fn(NamespaceInformation $info): string => $info->getNamespace(),
                $namespaces,
            );
        }

        return array_map(
            fn(string $filename): string => $this
                ->buildFacade
                ->getNamespaceFromFile($filename)
                ->getNamespace(),
            $paths,
        );
    }
}
