<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Api\ApiFacade;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Filesystem\FilesystemFacade;
use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

/**
 * @internal
 */
final class RunProvider extends AbstractProvider
{
    #[Provides(CommandFacadeInterface::class)]
    public function commandFacade(Container $container): CommandFacadeInterface
    {
        return $container->getLocator()->getRequired(CommandFacade::class);
    }

    #[Provides(CompilerFacadeInterface::class)]
    public function compilerFacade(Container $container): CompilerFacadeInterface
    {
        return $container->getLocator()->getRequired(CompilerFacade::class);
    }

    #[Provides(BuildFacadeInterface::class)]
    public function buildFacade(Container $container): BuildFacadeInterface
    {
        return $container->getLocator()->getRequired(BuildFacade::class);
    }

    #[Provides(ApiFacadeInterface::class)]
    public function apiFacade(Container $container): ApiFacadeInterface
    {
        return $container->getLocator()->getRequired(ApiFacade::class);
    }

    #[Provides(FilesystemFacadeInterface::class)]
    public function filesystemFacade(Container $container): FilesystemFacadeInterface
    {
        return $container->getLocator()->getRequired(FilesystemFacade::class);
    }
}
