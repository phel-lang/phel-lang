<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Runner;

use Phel\Build\BuildFacadeInterface;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;

class NamespaceRunner implements NamespaceRunnerInterface
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private BuildFacadeInterface $buildFacade,
    ) {
    }

    public function run(string $namespace): void
    {
        GlobalEnvironment::setMainNamespace($namespace);

        $this->commandFacade->registerExceptionHandler();

        $namespaceInformation = $this->buildFacade->getDependenciesForNamespace(
            [
                ...$this->commandFacade->getSourceDirectories(),
                ...$this->commandFacade->getVendorSourceDirectories(),
            ],
            [$namespace, 'phel\\core'],
        );

        foreach ($namespaceInformation as $info) {
            $this->buildFacade->evalFile($info->getFile());
        }
    }
}
