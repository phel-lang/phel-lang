<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phar;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Domain\NamespacesLoaderInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

use function in_array;

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
        $directories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        // Add core Phel files directory when running from PHAR,
        // but only if not already present (avoids duplicate namespace registration)
        if (str_starts_with(__FILE__, 'phar://')) {
            $pharSrcDir = Phar::running(true) . '/src/phel';
            if (!in_array($pharSrcDir, $directories, true)) {
                $directories[] = $pharSrcDir;
            }
        }

        return $this->buildFacade->getNamespaceFromDirectories($directories);
    }
}
