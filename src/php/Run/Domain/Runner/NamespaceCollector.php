<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Runner;

use Phel\Build\BuildFacadeInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Command\CommandFacadeInterface;
use Phel\Run\Domain\Test\CannotFindAnyTestsException;

final readonly class NamespaceCollector
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
    ) {
    }

    /**
     * @return list<NamespaceInformation>
     */
    public function getDependenciesFromPaths(array $paths): array
    {
        $namespaces = $this->getNamespacesFromPaths($paths);
        if ($namespaces === []) {
            throw CannotFindAnyTestsException::inPaths($paths);
        }

        $namespaces[] = 'phel\\test';

        return $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getTestDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            $namespaces,
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
                static fn (NamespaceInformation $info): string => $info->getNamespace(),
                $namespaces,
            );
        }

        return array_map(
            fn (string $filename): string => $this->buildFacade->getNamespaceFromFile($filename)->getNamespace(),
            $paths,
        );
    }
}
