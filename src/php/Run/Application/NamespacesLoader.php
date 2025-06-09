<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phar;
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
     * @return list<NamespaceInformation>
     */
    public function getLoadedNamespaces(): array
    {
        $directories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        // Add core Phel files directory when running from PHAR
        if (str_starts_with(__FILE__, 'phar://')) {
            $directories[] = Phar::running(true) . '/src/phel';
        }

        return $this->buildFacade->getNamespaceFromDirectories($directories);
    }
}
