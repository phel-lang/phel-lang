<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Build\BuildFacadeInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Command\CommandFacadeInterface;
use Phel\Run\Domain\NamespacesLoaderInterface;

final readonly class NamespacesLoader implements NamespacesLoaderInterface
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private BuildFacadeInterface $buildFacade,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getLoadedNamespaces(): array
    {
        $namespaceInfos = $this->buildFacade->getNamespaceFromDirectories([
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ]);

        return array_map(
            static fn (NamespaceInformation $info): string => $info->getNamespace(),
            $namespaceInfos,
        );
    }
}
