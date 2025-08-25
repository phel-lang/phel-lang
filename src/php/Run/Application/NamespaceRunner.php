<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Run\Domain\Runner\NamespaceRunnerInterface;

final readonly class NamespaceRunner implements NamespaceRunnerInterface
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private BuildFacadeInterface $buildFacade,
    ) {
    }

    /**
     * @param list<string> $importPaths
     */
    public function run(string $namespace, array $importPaths = []): void
    {
        $directories = [
            ...$importPaths,
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            $directories,
            [$namespace, 'phel\\core'],
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }
}
