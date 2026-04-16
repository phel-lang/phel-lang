<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Filesystem\FilesystemFacade;

final class CompilerProvider extends AbstractProvider
{
    public const string FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    #[Provides(self::FACADE_FILESYSTEM)]
    public function filesystemFacade(Container $container): FilesystemFacade
    {
        return $container->getLocator()->getRequired(FilesystemFacade::class);
    }
}
