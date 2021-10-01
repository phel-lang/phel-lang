<?php

declare(strict_types=1);

namespace Phel\Run\Runner;

use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Run\Finder\DirectoryFinder;

class NamespaceRunner implements NamespaceRunnerInterface
{
    private CommandFacadeInterface $commandFacade;
    private BuildFacadeInterface $buildFacade;
    private DirectoryFinder $directoryFinder;

    public function __construct(
        CommandFacadeInterface $commandFacade,
        BuildFacadeInterface $buildFacade,
        DirectoryFinder $directoryFinder
    ) {
        $this->commandFacade = $commandFacade;
        $this->buildFacade = $buildFacade;
        $this->directoryFinder = $directoryFinder;
    }

    public function run(string $namespace): void
    {
        $this->commandFacade->registerExceptionHandler();

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->directoryFinder->getSourceDirectories(),
                ...$this->directoryFinder->getVendorSourceDirectories(),
            ],
            [$namespace, 'phel\\core']
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }
}
