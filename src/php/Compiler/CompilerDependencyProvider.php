<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Filesystem\FilesystemFacade;

final class CompilerDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::FACADE_FILESYSTEM, static function (Container $container) {
            return $container->getLocator()->get(FilesystemFacade::class);
        });
    }
}
