<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Compiler\Infrastructure\CompiledCodeCache;
use Phel\Filesystem\FilesystemFacade;

final class CompilerProvider extends AbstractProvider
{
    public const string FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    public const string COMPILED_CODE_CACHE = 'COMPILED_CODE_CACHE';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(
            self::FACADE_FILESYSTEM,
            static fn (Container $container) => $container->getLocator()->get(FilesystemFacade::class),
        );

        $container->set(
            self::COMPILED_CODE_CACHE,
            static fn (Container $container): CompiledCodeCache => new CompiledCodeCache(
                $container->get(self::FACADE_FILESYSTEM),
            ),
        );
    }
}
