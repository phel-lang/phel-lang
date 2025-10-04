<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Run\Domain\Runner\NamespaceRunnerInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

final readonly class NamespaceRunner implements NamespaceRunnerInterface
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private BuildFacadeInterface $buildFacade,
    ) {
    }

    public function run(string $namespace): void
    {
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
