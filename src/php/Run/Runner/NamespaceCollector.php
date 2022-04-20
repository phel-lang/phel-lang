<?php

declare(strict_types=1);

namespace Phel\Run\Runner;

use Phel\Build\BuildFacadeInterface;
use Phel\Build\Extractor\NamespaceInformation;
use Phel\Command\CommandFacadeInterface;
use Phel\Run\Domain\Test\CannotFindAnyTestsException;

final class NamespaceCollector
{
    private BuildFacadeInterface $buildFacade;

    private CommandFacadeInterface $commandFacade;

    public function __construct(
        BuildFacadeInterface $buildFacade,
        CommandFacadeInterface $commandFacade
    ) {
        $this->buildFacade = $buildFacade;
        $this->commandFacade = $commandFacade;
    }

    /**
     * @return list<NamespaceInformation>
     */
    public function getDependenciesFromPaths(array $paths): array
    {
        $namespaces = $this->getNamespacesFromPaths($paths);
        if (empty($namespaces)) {
            throw CannotFindAnyTestsException::inPaths($paths);
        }
        $namespaces[] = 'phel\\test';

        return $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getTestDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            $namespaces
        );
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function getNamespacesFromPaths(array $paths): array
    {
        if (empty($paths)) {
            $namespaces = $this->buildFacade->getNamespaceFromDirectories(
                $this->commandFacade->getTestDirectories()
            );

            return array_map(
                static fn (NamespaceInformation $info): string => $info->getNamespace(),
                $namespaces
            );
        }

        return array_map(
            fn (string $filename): string => $this->buildFacade->getNamespaceFromFile($filename)->getNamespace(),
            $paths
        );
    }
}
