<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Domain\NamespacesLoaderInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

final readonly class NamespacesLoader implements NamespacesLoaderInterface
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private BuildFacadeInterface $buildFacade,
    ) {}

    /**
     * @return list<NamespaceInformation>
     */
    public function getLoadedNamespaces(): array
    {
        // The phel core library directory is already prepended to the source
        // directories by `CommandConfig::getCodeDirs()` (works for phar, vendor,
        // and source-tree installs), so no phar-specific fallback is needed here.
        $directories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        return $this->buildFacade->getNamespaceFromDirectories($directories);
    }
}
