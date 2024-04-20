<?php

declare(strict_types=1);

namespace Phel\Transpiler;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Filesystem\FilesystemFacade;

final class TranspilerDependencyProvider extends AbstractDependencyProvider
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
