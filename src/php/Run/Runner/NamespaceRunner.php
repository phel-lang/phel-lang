<?php

declare(strict_types=1);

namespace Phel\Run\Runner;

use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;

class NamespaceRunner implements NamespaceRunnerInterface
{
    private CommandFacadeInterface $commandFacade;
    private BuildFacadeInterface $buildFacade;

    public function __construct(
        CommandFacadeInterface $commandFacade,
        BuildFacadeInterface $buildFacade
    ) {
        $this->commandFacade = $commandFacade;
        $this->buildFacade = $buildFacade;
    }

    public function run(string $namespace): void
    {
        $this->commandFacade->registerExceptionHandler();

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            [$namespace, 'phel\\core']
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }
}
