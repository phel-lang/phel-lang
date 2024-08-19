<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Filesystem\FilesystemFacade;

final class CompilerProvider extends AbstractProvider
{
    public const FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(
            self::FACADE_FILESYSTEM,
            static fn (Container $container) => $container->getLocator()->get(FilesystemFacade::class),
        );
    }
}
