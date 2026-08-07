<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Filesystem\FilesystemFacade;
use Phel\Filesystem\FilesystemFacadeInterface;

/**
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: CompilerConfig::class)]
final class CompilerProvider extends AbstractProvider
{
    #[Provides(FilesystemFacadeInterface::class)]
    public function filesystemFacade(Container $container): FilesystemFacadeInterface
    {
        return $container->getLocator()->getRequired(FilesystemFacade::class);
    }
}
